<?php
/**
 * Template: Presupuestos por Área
 * Fase 5, Item 5.1 — Refactorizado Fase 8.3+ (presupuesto por área, categoría como referencia opcional)
 */

if ( ! defined( 'ABSPATH' ) ) exit;
// Usuarios con aura_areas_view_own pueden ver el presupuesto de su propia área
if ( ! current_user_can( 'aura_finance_view_all' ) && ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_view_own' ) ) {
    wp_die( esc_html__( 'Sin permisos para ver presupuestos', 'aura-suite' ) );
}
$can_manage = current_user_can( 'aura_finance_create' ) || current_user_can( 'manage_options' );
$can_delete = current_user_can( 'aura_finance_delete_all' ) || current_user_can( 'manage_options' );

// Áreas activas para filtro y formulario (Fase 8.1)
global $wpdb;
$_aura_areas = $wpdb->get_results(
    "SELECT id, name, color FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY sort_order ASC, name ASC"
);
// Si el usuario solo puede ver su propia área, restringir el select
$_aura_user_area_only = (
    ! current_user_can( 'manage_options' ) &&
    ! current_user_can( 'aura_areas_view_all' ) &&
    current_user_can( 'aura_areas_view_own' )
);
$_aura_user_area_id = 0;
if ( $_aura_user_area_only ) {
    $_aura_user_area_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}aura_areas WHERE responsible_user_id = %d AND status = 'active' LIMIT 1",
        get_current_user_id()
    ) );
}
?>
<div class="wrap aura-budgets-wrap">

    <div class="aura-budgets-header">
        <h1>
            <span class="dashicons dashicons-chart-pie"></span>
            <?php esc_html_e( 'Presupuestos por Área', 'aura-suite' ); ?>
        </h1>
        <?php if ( $can_manage ) : ?>
        <button type="button" class="button button-primary" id="aura-new-budget-btn">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( '+ Nuevo Presupuesto', 'aura-suite' ); ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- Resumen rápido -->
    <div class="aura-budget-summary-bar" id="aura-budget-summary-bar">
        <div class="aura-summary-skeleton"></div>
    </div>

    <!-- Filtros -->
    <div class="aura-budgets-filters">
        <select id="aura-filter-period">
            <option value=""><?php esc_html_e( 'Todos los períodos', 'aura-suite' ); ?></option>
            <option value="monthly"><?php esc_html_e( 'Mensual', 'aura-suite' ); ?></option>
            <option value="quarterly"><?php esc_html_e( 'Trimestral', 'aura-suite' ); ?></option>
            <option value="semestral"><?php esc_html_e( 'Semestral', 'aura-suite' ); ?></option>
            <option value="yearly"><?php esc_html_e( 'Anual', 'aura-suite' ); ?></option>
        </select>
        <select id="aura-filter-status">
            <option value=""><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
            <option value="ok"><?php esc_html_e( 'En buen estado (< 70%)', 'aura-suite' ); ?></option>
            <option value="warning"><?php esc_html_e( 'Advertencia (70-89%)', 'aura-suite' ); ?></option>
            <option value="critical"><?php esc_html_e( 'Crítico (90-99%)', 'aura-suite' ); ?></option>
            <option value="overrun"><?php esc_html_e( 'Sobrepasado (> 100%)', 'aura-suite' ); ?></option>
        </select>
        <?php if ( ! empty( $_aura_areas ) ) : ?>
        <select id="aura-filter-area" <?php echo $_aura_user_area_only ? 'disabled' : ''; ?>>
            <option value=""><?php esc_html_e( 'Todas las áreas', 'aura-suite' ); ?></option>
            <?php foreach ( $_aura_areas as $_a ) : ?>
            <option value="<?php echo esc_attr( $_a->id ); ?>"
                    <?php if ( $_aura_user_area_only ) selected( $_aura_user_area_id, $_a->id ); ?>>
                <?php echo esc_html( $_a->name ); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php if ( $_aura_user_area_only && $_aura_user_area_id ) : ?>
        <input type="hidden" id="aura-filter-area-forced" value="<?php echo esc_attr( $_aura_user_area_id ); ?>">
        <?php endif; ?>
        <?php endif; ?>
        <button type="button" class="button" id="aura-filters-clear"><?php esc_html_e( 'Limpiar', 'aura-suite' ); ?></button>
    </div>

    <!-- Tabla de presupuestos -->
    <div id="aura-budgets-loading" class="aura-budgets-loading">
        <span class="spinner is-active"></span>
        <?php esc_html_e( 'Cargando presupuestos…', 'aura-suite' ); ?>
    </div>

    <div id="aura-budgets-list" style="display:none"></div>

    <p id="aura-budgets-empty" style="display:none;color:#8c8f94;font-style:italic">
        <?php esc_html_e( 'No hay presupuestos. Crea el primero con el botón de arriba.', 'aura-suite' ); ?>
    </p>

</div><!-- .aura-budgets-wrap -->


<!-- ================================================================
     MODAL: Crear / Editar Presupuesto
     ============================================================== -->
<div id="aura-budget-modal" class="aura-modal-overlay" style="display:none" role="dialog" aria-modal="true">
    <div class="aura-modal aura-budget-form-modal">
        <div class="aura-modal-header">
            <h2 id="aura-budget-modal-title"><?php esc_html_e( 'Nuevo Presupuesto', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-modal-close" id="aura-budget-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>

        <form id="aura-budget-form" autocomplete="off">
            <input type="hidden" id="budget_id" name="id" value="0">

            <div class="aura-form-grid">

                <!-- Área / Programa (REQUERIDO — Fase 8.3+) -->
                <div class="aura-form-group full-width">
                    <label for="budget_area_id">
                        <?php esc_html_e( 'Área / Programa *', 'aura-suite' ); ?>
                    </label>
                    <?php if ( ! empty( $_aura_areas ) ) : ?>
                    <select id="budget_area_id" name="area_id" required
                            <?php if ( $_aura_user_area_only ) echo 'disabled'; ?>>
                        <option value=""><?php esc_html_e( '— Seleccione un área —', 'aura-suite' ); ?></option>
                        <?php foreach ( $_aura_areas as $_a ) : ?>
                        <option value="<?php echo esc_attr( $_a->id ); ?>"
                                <?php if ( $_aura_user_area_only ) selected( $_aura_user_area_id, $_a->id ); ?>>
                            <?php echo esc_html( $_a->name ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ( $_aura_user_area_only && $_aura_user_area_id ) : ?>
                    <input type="hidden" name="area_id" value="<?php echo esc_attr( $_aura_user_area_id ); ?>">
                    <?php endif; ?>
                    <?php else : ?>
                    <p class="description" style="color:#d63638;">
                        <?php esc_html_e( 'No hay áreas activas. Crea al menos una área antes de crear presupuestos.', 'aura-suite' ); ?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Categoría (OPCIONAL — solo como referencia del tipo de gasto) -->
                <div class="aura-form-group full-width">
                    <label for="budget_category_id">
                        <?php esc_html_e( 'Categoría de referencia', 'aura-suite' ); ?>
                        <span style="font-weight:400;color:#8c8f94;font-size:12px;margin-left:4px;">(<?php esc_html_e( 'opcional', 'aura-suite' ); ?>)</span>
                    </label>
                    <select id="budget_category_id" name="category_id">
                        <option value=""><?php esc_html_e( '— Sin categoría de referencia —', 'aura-suite' ); ?></option>
                        <?php
                        global $wpdb;
                        $cats = $wpdb->get_results( "SELECT id, name, type, color FROM {$wpdb->prefix}aura_finance_categories WHERE is_active = 1 AND type = 'expense' ORDER BY name" );
                        foreach ( $cats as $cat ) :
                        ?>
                            <option value="<?php echo esc_attr( $cat->id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'El presupuesto se mide a nivel de área. La categoría de referencia es solo informativa.', 'aura-suite' ); ?>
                    </p>
                </div>

                <!-- Monto -->
                <div class="aura-form-group">
                    <label for="budget_amount"><?php esc_html_e( 'Monto del presupuesto *', 'aura-suite' ); ?></label>
                    <div class="aura-input-prefix">
                        <span>$</span>
                        <input type="number" id="budget_amount" name="budget_amount" step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                </div>

                <!-- Período -->
                <div class="aura-form-group">
                    <label><?php esc_html_e( 'Tipo de período *', 'aura-suite' ); ?></label>
                    <div class="aura-period-radios">
                        <label class="aura-radio-pill selected">
                            <input type="radio" name="period_type" value="monthly" checked>
                            <?php esc_html_e( 'Mensual', 'aura-suite' ); ?>
                        </label>
                        <label class="aura-radio-pill">
                            <input type="radio" name="period_type" value="quarterly">
                            <?php esc_html_e( 'Trimestral', 'aura-suite' ); ?>
                        </label>
                        <label class="aura-radio-pill">
                            <input type="radio" name="period_type" value="semestral">
                            <?php esc_html_e( 'Semestral', 'aura-suite' ); ?>
                        </label>
                        <label class="aura-radio-pill">
                            <input type="radio" name="period_type" value="yearly">
                            <?php esc_html_e( 'Anual', 'aura-suite' ); ?>
                        </label>
                    </div>
                </div>

                <!-- Desde -->
                <div class="aura-form-group">
                    <label for="budget_start_date"><?php esc_html_e( 'Fecha inicio *', 'aura-suite' ); ?></label>
                    <input type="date" id="budget_start_date" name="start_date" required data-allow-future="true">
                </div>

                <!-- Hasta -->
                <div class="aura-form-group">
                    <label for="budget_end_date"><?php esc_html_e( 'Fecha fin *', 'aura-suite' ); ?></label>
                    <input type="date" id="budget_end_date" name="end_date" required data-allow-future="true">
                </div>

                <!-- Alertas -->
                <div class="aura-form-group full-width">
                    <label><?php esc_html_e( 'Alertas', 'aura-suite' ); ?></label>
                    <div class="aura-alert-options">
                        <label class="aura-check-row">
                            <input type="checkbox" name="alert_on_threshold" id="alert_on_threshold" checked>
                            <?php esc_html_e( 'Enviar alerta al llegar a', 'aura-suite' ); ?>
                            <input type="number" id="alert_threshold" name="alert_threshold" value="80" min="1" max="99" style="width:60px;margin:0 4px">
                            %
                        </label>
                        <label class="aura-check-row">
                            <input type="checkbox" name="alert_on_exceed" id="alert_on_exceed" checked>
                            <?php esc_html_e( 'Enviar alerta al sobrepasar el 100%', 'aura-suite' ); ?>
                        </label>
                    </div>
                </div>

                <!-- Notificar a -->
                <div class="aura-form-group full-width">
                    <label><?php esc_html_e( 'Notificar a', 'aura-suite' ); ?></label>
                    <div class="aura-notify-options">
                        <label class="aura-check-row">
                            <input type="checkbox" name="notify_creator" id="notify_creator" checked>
                            <?php esc_html_e( 'Creador del presupuesto', 'aura-suite' ); ?>
                        </label>
                        <label class="aura-check-row">
                            <input type="checkbox" name="notify_admins" id="notify_admins" checked>
                            <?php esc_html_e( 'Administradores del sistema', 'aura-suite' ); ?>
                        </label>
                        <label class="aura-check-row" style="display:block">
                            <input type="checkbox" id="notify_extra_toggle">
                            <?php esc_html_e( 'Emails adicionales (separados por coma)', 'aura-suite' ); ?>
                            <input type="email" id="notify_emails" name="notify_emails" placeholder="email1@x.com, email2@x.com"
                                   style="display:none;width:100%;margin-top:6px" multiple>
                        </label>
                    </div>
                </div>

            </div><!-- .aura-form-grid -->

            <div class="aura-modal-footer">
                <button type="button" class="button" id="aura-budget-cancel"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
                <button type="submit" class="button button-primary" id="aura-budget-save">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e( 'Guardar Presupuesto', 'aura-suite' ); ?>
                </button>
            </div>

            <div class="aura-form-error" id="aura-budget-form-error" style="display:none"></div>
        </form>
    </div>
</div>

<!-- ================================================================
     MODAL: Detalle de Presupuesto
     ============================================================== -->
<div id="aura-budget-detail-modal" class="aura-modal-overlay" style="display:none" role="dialog" aria-modal="true">
    <div class="aura-modal aura-budget-detail-modal">
        <div class="aura-modal-header">
            <h2 id="aura-detail-title"><?php esc_html_e( 'Detalle del Presupuesto', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-modal-close" id="aura-detail-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>

        <div class="aura-detail-grid">

            <!-- Gráfico y stats -->
            <div class="aura-detail-left">
                <div class="aura-detail-chart-wrap">
                    <div id="aura-budget-donut-chart"></div>
                </div>
                <div class="aura-detail-stats" id="aura-detail-stats"></div>

                <!-- Ajuste rápido -->
                <div class="aura-adjust-section" id="aura-adjust-section">
                    <h4><?php esc_html_e( 'Ajustar presupuesto', 'aura-suite' ); ?></h4>
                    <div class="aura-adjust-form">
                        <select id="adj_type">
                            <option value="percent"><?php esc_html_e( '%', 'aura-suite' ); ?></option>
                            <option value="amount"><?php esc_html_e( 'Monto fijo', 'aura-suite' ); ?></option>
                        </select>
                        <input type="number" id="adj_value" step="0.01" placeholder="10" style="width:80px">
                        <button type="button" class="button button-small" id="aura-apply-adjust">
                            <?php esc_html_e( 'Aplicar', 'aura-suite' ); ?>
                        </button>
                    </div>
                    <p class="description"><?php esc_html_e( 'Ej: +10 para aumentar 10%; -500 para reducir $500', 'aura-suite' ); ?></p>
                </div>
            </div>

            <!-- Historial y transacciones (panel derecho con tabs) -->
            <div class="aura-detail-right">

                <!-- Gráfico histórico -->
                <h4><?php esc_html_e( 'Presupuesto vs. Ejecutado (últimos 6 períodos)', 'aura-suite' ); ?></h4>
                <div id="aura-budget-history-chart"></div>

                <!-- Tabs: Transacciones / Análisis por Categoría -->
                <div class="aura-detail-tabs" style="margin-top:18px;">
                    <div class="aura-tab-nav" role="tablist">
                        <button type="button" class="aura-tab-btn aura-tab-active"
                                role="tab" aria-selected="true"
                                data-tab="aura-tab-transactions">
                            <span class="dashicons dashicons-list-view"></span>
                            <?php esc_html_e( 'Transacciones del período', 'aura-suite' ); ?>
                        </button>
                        <button type="button" class="aura-tab-btn"
                                role="tab" aria-selected="false"
                                data-tab="aura-tab-analysis">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <?php esc_html_e( 'Análisis por Categoría', 'aura-suite' ); ?>
                        </button>
                    </div>

                    <!-- Panel: Transacciones -->
                    <div id="aura-tab-transactions" class="aura-tab-panel" role="tabpanel">
                        <div class="aura-detail-tx-wrap">
                            <table class="widefat striped" id="aura-detail-transactions">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Descripción', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Categoría', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Monto', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="aura-detail-tx-body">
                                    <tr><td colspan="5" class="aura-loading"><?php esc_html_e( 'Cargando…', 'aura-suite' ); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Panel: Análisis por Categoría -->
                    <div id="aura-tab-analysis" class="aura-tab-panel" role="tabpanel" style="display:none;">
                        <div id="aura-cat-breakdown-loading" style="text-align:center;padding:20px;">
                            <span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span>
                            <?php esc_html_e( 'Cargando análisis…', 'aura-suite' ); ?>
                        </div>
                        <div id="aura-cat-breakdown-content" style="display:none;">
                            <!-- Gráfico de barras CSS -->
                            <div id="aura-cat-bar-chart" style="margin-bottom:16px;"></div>
                            <!-- Tabla de desglose -->
                            <table class="widefat striped" id="aura-cat-breakdown-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Categoría', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'N° Trans.', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Ejecutado', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( '% del Total', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Barra', 'aura-suite' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="aura-cat-breakdown-body"></tbody>
                                <tfoot id="aura-cat-breakdown-foot"></tfoot>
                            </table>
                            <!-- Alerta contextual -->
                            <div id="aura-cat-breakdown-alert" style="display:none;margin-top:12px;"></div>
                        </div>
                        <div id="aura-cat-breakdown-empty" style="display:none;padding:12px;color:#8c8f94;font-style:italic;">
                            <?php esc_html_e( 'Sin transacciones aprobadas en este período.', 'aura-suite' ); ?>
                        </div>
                    </div>

                </div><!-- .aura-detail-tabs -->
            </div>

        </div><!-- .aura-detail-grid -->
    </div>
</div>
