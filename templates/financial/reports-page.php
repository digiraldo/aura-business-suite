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
$users_list   = $can_view_all ? Aura_Roles_Manager::get_aura_users( [ 'fields' => [ 'ID', 'display_name' ] ], 'aura_finance_' ) : [];

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

    <div class="aura-reports-layout" id="aura-reports-layout">

        <!-- ══════════════════ PANEL IZQUIERDO: Configuración ══════════════════ -->
        <aside class="aura-reports-sidebar aura-filters-card" id="aura-reports-sidebar">

            <div class="aura-filters-header">
                <div class="aura-filters-header-title">
                    <span class="dashicons dashicons-filter" style="color: #3b82f6;"></span>
                    <h3 style="margin:0;font-size:13px;font-weight:700;color:#1e293b;letter-spacing:0.3px;text-transform:uppercase;"><?php esc_html_e( 'Filtros y Configuración', 'aura-suite' ); ?></h3>
                </div>
                <button type="button" class="button-link aura-toggle-sidebar-btn" id="toggle-filters" title="<?php esc_attr_e( 'Ocultar filtros', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </button>
            </div>

            <div class="aura-filters-body">
                <form id="aura-report-form" autocomplete="off">
                    <?php wp_nonce_field( 'aura_reports_nonce', '_reports_nonce_field', false ); ?>

                    <!-- Sección: Tipo de Reporte -->
                    <div class="filter-section">
                        <h4>
                            <span class="dashicons dashicons-chart-bar" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#3b82f6;"></span>
                            <?php esc_html_e( 'Tipo de Reporte', 'aura-suite' ); ?>
                        </h4>
                        <div class="aura-select-wrap">
                            <select name="report_type" id="report_type" class="aura-select widefat" required>
                                <option value=""><?php esc_html_e( '— Seleccionar Reporte —', 'aura-suite' ); ?></option>
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
                    </div>

                    <!-- Sección: Tipos de Transacción (Selector Múltiple para Análisis por Categoría) -->
                    <div class="filter-section" id="group-transaction-types" style="display:none;">
                        <h4>
                            <span class="dashicons dashicons-tag" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#3b82f6;"></span>
                            <?php esc_html_e( 'Tipos de Movimiento', 'aura-suite' ); ?>
                        </h4>
                        <div class="aura-filter-chips-grid aura-filter-chips-3col">
                            <label class="aura-chip-checkbox aura-chip-income active">
                                <input type="checkbox" name="transaction_types[]" value="income" checked>
                                <span class="chip-dot"></span>
                                <span class="chip-text"><?php esc_html_e( 'Ingreso', 'aura-suite' ); ?></span>
                            </label>
                            <label class="aura-chip-checkbox aura-chip-expense active">
                                <input type="checkbox" name="transaction_types[]" value="expense" checked>
                                <span class="chip-dot"></span>
                                <span class="chip-text"><?php esc_html_e( 'Egreso', 'aura-suite' ); ?></span>
                            </label>
                            <label class="aura-chip-checkbox aura-chip-capital active">
                                <input type="checkbox" name="transaction_types[]" value="capital" checked>
                                <span class="chip-dot"></span>
                                <span class="chip-text"><?php esc_html_e( 'CapEx', 'aura-suite' ); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- Sección: Rango de Fechas / Período -->
                    <div class="filter-section" id="group-dates">
                        <h4>
                            <span class="dashicons dashicons-calendar-alt" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#f59e0b;"></span>
                            <?php esc_html_e( 'Período y Fechas', 'aura-suite' ); ?>
                        </h4>
                        <div class="aura-date-range-row">
                            <div class="aura-date-input-wrap">
                                <label for="report_start"><?php esc_html_e( 'Desde', 'aura-suite' ); ?></label>
                                <input type="date" name="start" id="report_start" class="aura-input aura-datepicker-field"
                                       value="<?php echo esc_attr( date( 'Y-m-01' ) ); ?>">
                            </div>
                            <div class="aura-date-input-wrap">
                                <label for="report_end"><?php esc_html_e( 'Hasta', 'aura-suite' ); ?></label>
                                <input type="date" name="end" id="report_end" class="aura-input aura-datepicker-field"
                                       value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                            </div>
                        </div>
                        <div class="aura-quick-date-chips">
                            <button type="button" class="aura-date-preset-btn aura-preset-btn active" data-preset="month"><?php esc_html_e( 'Este Mes', 'aura-suite' ); ?></button>
                            <button type="button" class="aura-date-preset-btn aura-preset-btn" data-preset="prevmonth"><?php esc_html_e( 'Mes Ant.', 'aura-suite' ); ?></button>
                            <button type="button" class="aura-date-preset-btn aura-preset-btn" data-preset="quarter"><?php esc_html_e( 'Trimestre', 'aura-suite' ); ?></button>
                            <button type="button" class="aura-date-preset-btn aura-preset-btn" data-preset="year"><?php esc_html_e( 'Este Año', 'aura-suite' ); ?></button>
                        </div>
                    </div>

                    <!-- Sección: Estado -->
                    <div class="filter-section" id="group-status">
                        <h4>
                            <span class="dashicons dashicons-yes-alt" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#10b981;"></span>
                            <?php esc_html_e( 'Estado de Transacciones', 'aura-suite' ); ?>
                        </h4>
                        <div class="aura-select-wrap">
                            <select name="status" id="report_status" class="aura-select widefat">
                                <option value="approved"><?php esc_html_e( 'Aprobadas', 'aura-suite' ); ?></option>
                                <option value="all"><?php esc_html_e( 'Todas', 'aura-suite' ); ?></option>
                                <option value="pending"><?php esc_html_e( 'Pendientes', 'aura-suite' ); ?></option>
                                <option value="rejected"><?php esc_html_e( 'Rechazadas', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Sección: Área / Programa -->
                    <?php if ( ! empty( $_areas_for_report ) ) : ?>
                    <div class="filter-section" id="group-area" style="display:none;">
                        <h4>
                            <span class="dashicons dashicons-networking" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#8b5cf6;"></span>
                            <?php esc_html_e( 'Área / Programa', 'aura-suite' ); ?>
                        </h4>
                        <div class="aura-select-wrap">
                            <select name="area_id" id="report_area" class="aura-select widefat">
                                <option value="0"><?php esc_html_e( '— Todas las áreas —', 'aura-suite' ); ?></option>
                                <?php foreach ( $_areas_for_report as $_ra ) : ?>
                                    <option value="<?php echo esc_attr( $_ra->id ); ?>"><?php echo esc_html( $_ra->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small style="color:#64748b;margin-top:4px;display:block;font-size:11px;">
                            <?php esc_html_e( '"Detalle por Área" requiere seleccionar un área específica.', 'aura-suite' ); ?>
                        </small>
                    </div>
                    <?php endif; ?>

                    <!-- Sección: Creado por -->
                    <?php if ( $can_view_all ) : ?>
                    <div class="filter-section" id="group-creator" style="display:none;">
                        <h4>
                            <span class="dashicons dashicons-admin-users" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#06b6d4;"></span>
                            <?php esc_html_e( 'Creado por (Usuario)', 'aura-suite' ); ?>
                        </h4>
                        <div class="aura-select-wrap">
                            <select name="created_by" id="report_creator" class="aura-select widefat">
                                <option value="0"><?php esc_html_e( 'Todos los usuarios', 'aura-suite' ); ?></option>
                                <?php foreach ( $users_list as $u ) : ?>
                                    <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $u->display_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Botón Generar Acción -->
                    <div class="filter-actions" style="margin-top:12px;padding-top:0;border-top:none;">
                        <button type="submit" id="btn-generate" class="button button-primary aura-btn-generate" disabled>
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e( 'Generar Reporte', 'aura-suite' ); ?>
                        </button>
                    </div>
                </form>

                <!-- Exportación Rápida -->
                <div class="filter-section" id="aura-export-card" style="display:none;margin-top:14px;">
                    <h4>
                        <span class="dashicons dashicons-download" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#10b981;"></span>
                        <?php esc_html_e( 'Exportar Resultados', 'aura-suite' ); ?>
                    </h4>
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

                <!-- Mis configuraciones guardadas -->
                <div class="filter-section" style="margin-top:14px;">
                    <h4>
                        <span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-right:3px;color:#6366f1;"></span>
                        <?php esc_html_e( 'Mis Configuraciones', 'aura-suite' ); ?>
                    </h4>
                    <div id="aura-saved-configs" style="margin-bottom:8px;">
                        <p class="aura-empty-msg" style="font-size:12px;color:#94a3b8;margin:0;"><?php esc_html_e( 'No hay configuraciones guardadas.', 'aura-suite' ); ?></p>
                    </div>
                    <div class="aura-save-config-form" style="display:flex;flex-direction:column;gap:6px;">
                        <input type="text" id="config-name-input" class="aura-input"
                               placeholder="<?php esc_attr_e( 'Nombre del preset…', 'aura-suite' ); ?>" maxlength="60" style="font-size:12px;padding:6px 8px;">
                        <button type="button" id="btn-save-config" class="button button-secondary aura-btn-save-config" style="font-size:12px;display:flex;align-items:center;justify-content:center;gap:4px;">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e( 'Guardar filtro actual', 'aura-suite' ); ?>
                        </button>
                    </div>
                </div>
            </div>

        </aside>

        <!-- ══════════════════ PANEL DERECHO: Vista del Reporte ══════════════════ -->
        <main class="aura-reports-main" id="aura-report-output">

            <!-- Barra superior para restaurar filtros y exportar cuando está oculto -->
            <div class="aura-reports-top-bar" id="aura-reports-top-bar">
                <div class="aura-top-bar-left">
                    <button type="button" class="button" id="show-filters" style="display: none;">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e( 'Mostrar Filtros', 'aura-suite' ); ?>
                    </button>
                </div>
                <div class="aura-top-bar-right" id="aura-top-bar-export" style="display: none;">
                    <button type="button" class="aura-export-btn aura-export-btn--csv" id="btn-top-export-csv" title="<?php esc_attr_e( 'Exportar CSV', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-media-spreadsheet"></span> CSV
                    </button>
                    <button type="button" class="aura-export-btn aura-export-btn--excel" id="btn-top-export-excel" title="<?php esc_attr_e( 'Exportar Excel (.xlsx)', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-media-spreadsheet"></span> Excel (.xlsx)
                    </button>
                    <button type="button" class="aura-export-btn aura-export-btn--print" id="btn-top-print" title="<?php esc_attr_e( 'Imprimir / PDF', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Imprimir / PDF', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

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

                <!-- Cabecera de impresión oficial -->
                <div class="aura-print-header">
                    <div class="aura-print-header__brand-box">
                        <?php
                        $logo_url = get_site_icon_url( 80 );
                        if ( $logo_url ) {
                            echo '<img src="' . esc_url( $logo_url ) . '" alt="" class="aura-print-logo" loading="eager">';
                        }
                        ?>
                        <div class="aura-print-header__brand-text">
                            <h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
                            <span class="aura-print-header__tagline"><?php esc_html_e( 'MÓDULO FINANCIERO — REPORTE OFICIAL', 'aura-suite' ); ?></span>
                        </div>
                    </div>
                    <div class="aura-print-header__report-card">
                        <h1 id="print-report-title"><?php esc_html_e( 'Reporte Financiero', 'aura-suite' ); ?></h1>
                        <div class="aura-print-period-pill">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <span id="print-report-period"></span>
                        </div>
                    </div>
                    <div class="aura-print-header__meta-box">
                        <div class="aura-print-meta-item">
                            <span class="aura-print-meta-label"><?php esc_html_e( 'Generado por', 'aura-suite' ); ?>:</span>
                            <strong class="aura-print-meta-value"><?php echo esc_html( wp_get_current_user()->display_name ); ?></strong>
                        </div>
                        <div class="aura-print-meta-item">
                            <span class="aura-print-meta-label"><?php esc_html_e( 'Fecha de Emisión', 'aura-suite' ); ?>:</span>
                            <span class="aura-print-meta-value" id="print-report-date"></span>
                        </div>
                        <div class="aura-print-meta-item">
                            <span class="aura-print-meta-label"><?php esc_html_e( 'Carácter', 'aura-suite' ); ?>:</span>
                            <span class="aura-print-badge-official"><?php esc_html_e( 'Documento Oficial', 'aura-suite' ); ?></span>
                        </div>
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

                <!-- Pie del reporte (Pantalla) -->
                <div class="aura-report-footer">
                    <small><?php esc_html_e( 'Reporte generado automáticamente por Aura Business Suite', 'aura-suite' ); ?> — <?php echo esc_html( get_bloginfo( 'name' ) ); ?></small>
                </div>

                <!-- Pie de Impresión Oficial (@media print) -->
                <div class="aura-print-footer">
                    <div class="aura-print-footer__left">
                        <span class="dashicons dashicons-shield-alt"></span>
                        <?php esc_html_e( 'Documento confidencial generado por Aura Business Suite. Válido para propósitos administrativos y contables.', 'aura-suite' ); ?>
                    </div>
                    <div class="aura-print-footer__right">
                        <?php echo esc_html( get_bloginfo( 'name' ) ); ?> &bull; <?php esc_html_e( 'Página 1', 'aura-suite' ); ?>
                    </div>
                </div>

            </div><!-- /#aura-report-content -->

        </main><!-- /.aura-reports-main -->

    </div><!-- /.aura-reports-layout -->

</div><!-- /.aura-reports-wrap -->
