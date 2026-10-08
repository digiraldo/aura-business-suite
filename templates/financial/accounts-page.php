<?php
/**
 * Template: Bancos y Cuentas
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="aura-app-wrapper aura-financial-accounts-page">
    <div class="wrap aura-app-context aura-accounts-context">
        <h1 class="wp-heading-inline screen-reader-text"><?php _e('Bancos y Cuentas', 'aura-suite'); ?></h1>
        <hr class="wp-header-end">
        <div id="aura-wp-notices-container" class="aura-wp-notices-container"></div>
        <!-- ── CABECERA PRINCIPAL CANÓNICA (HERO GLASS CARD) ──────────── -->
        <header class="aura-page-header hero-card aura-glass-card fade-up">
            <div class="aura-header-left" style="display: flex; align-items: center; gap: 18px; flex: 1 1 auto; min-width: 0;">
                <div class="aura-page-header__icon">
                    <span class="dashicons dashicons-bank" style="font-size: 26px; width: 26px; height: 26px;"></span>
                </div>
                <div class="aura-header-text" style="flex: 1 1 auto; min-width: 0;">
                    <div class="aura-title-with-badge" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <h1 class="aura-page-title" style="margin: 0; color: #ffffff !important; font-weight: 800; font-size: 1.45rem; line-height: 1.25;">
                            <?php _e('Bancos y Cuentas', 'aura-suite'); ?>
                        </h1>
                        <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Aquí ves saldos, movimientos clave y accesos rápidos. Usa los botones de arriba para registrar y esta pantalla para revisar.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>" style="color: #ffffff; border-color: rgba(255,255,255,0.4); background: rgba(255,255,255,0.15);">?</button>
                        <span class="badge badge-blue">
                            <span class="pulse-dot"></span>
                            <?php _e('Tesorería & Cajas', 'aura-suite'); ?>
                        </span>
                        <span class="badge badge-emerald">
                            <span class="pulse-dot"></span>
                            <?php _e('Multi-divisa (COP/USD/EUR)', 'aura-suite'); ?>
                        </span>
                    </div>
                    <p class="aura-page-subtitle hero-desc" style="margin: 6px 0 0; color: rgba(255, 255, 255, 0.88) !important; font-size: 0.88rem; line-height: 1.45;">
                        <?php _e('Administra dónde está el dinero, monitorea saldos por moneda, gestiona cajas chicas y define topes presupuestales desde una sola pantalla.', 'aura-suite'); ?>
                    </p>
                </div>
            </div>
            <div class="aura-header-right" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-left: auto; z-index: 10;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-dashboard' ) ); ?>" class="btn btn-glass btn-lift">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <span><?php esc_html_e( 'Volver al Dashboard', 'aura-suite' ); ?></span>
                </a>
            </div>
        </header>
        <div class="aura-layout">
        <!-- ── NAVBAR ── -->
        <nav class="aura-navbar aura-nav aura-glass-card">
            <div class="aura-navbar-menu">
                <a href="#" class="aura-navbar-item aura-tab-btn active" data-tab="tab-cuentas">
                    <span class="dashicons dashicons-bank"></span> <?php _e('Cuentas Bancarias', 'aura-suite'); ?>
                </a>
                <a href="#" class="aura-navbar-item aura-tab-btn" data-tab="tab-traspasos">
                    <span class="dashicons dashicons-randomize"></span> <?php _e('Traspasos', 'aura-suite'); ?>
                </a>
                <a href="#" class="aura-navbar-item aura-tab-btn" data-tab="tab-cajachica">
                    <span class="dashicons dashicons-money-alt"></span> <?php _e('Caja Chica', 'aura-suite'); ?>
                </a>
                <a href="#" class="aura-navbar-item aura-tab-btn" data-tab="tab-reembolsos">
                    <span class="dashicons dashicons-tickets-alt"></span> <?php _e('Reembolsos', 'aura-suite'); ?>
                </a>
                <a href="#" class="aura-navbar-item aura-tab-btn" data-tab="tab-presupuestos">
                    <span class="dashicons dashicons-chart-pie"></span> <?php _e('Presupuestos', 'aura-suite'); ?>
                </a>
                <a href="#" class="aura-navbar-item aura-tab-btn" data-tab="tab-reportes">
                    <span class="dashicons dashicons-chart-bar"></span> <?php _e('Reportes y Cierre', 'aura-suite'); ?>
                </a>
            </div>
        </nav>
        <main class="aura-content">
            <!-- Feedback global -->
            <div id="aura-accounts-feedback" class="notice aura-hidden"></div>
            <!-- TAB: Cuentas -->
            <div id="tab-cuentas" class="aura-tab-panel active">
                <div class="aura-card-head">
                    <div>
                        <h2 class="aura-title-with-help"><?php _e('Listado de cuentas', 'aura-suite'); ?></h2>
                    </div>
                    <div class="aura-section-actions">
                        <button type="button" class="btn btn-secondary btn-lift" id="aura-accounts-export-btn">
                            <span class="dashicons dashicons-download" style="vertical-align:middle;"></span>
                            <?php _e('Exportar CSV', 'aura-suite'); ?>
                        </button>
                        <button type="button" class="btn btn-teal btn-lift" id="aura-transfer-open-btn" style="background: #0d9488; color: #fff;">
                            <span class="dashicons dashicons-randomize" style="vertical-align:middle;"></span>
                            <?php _e('Nuevo Traspaso', 'aura-suite'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-account-new-btn">
                            <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;"></span>
                            <?php _e('Nueva Cuenta', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>
                <!-- KPIs de Cuentas -->
                <div class="aura-report-kpis aura-kpis-top-spacer">
                    <div class="kpi-card kpi-indigo card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-bank"></span></div>
                        <span class="kpi-label"><?php _e('Total Cuentas', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-kpi-total-accounts">0</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Registradas', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-emerald card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-yes-alt"></span></div>
                        <span class="kpi-label"><?php _e('Cuentas Activas', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-kpi-active-accounts">0</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Operativas', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-blue card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-money-alt"></span></div>
                        <span class="kpi-label"><?php _e('Saldo Total (COP)', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-kpi-balance-cop">0</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Moneda Local', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-cyan card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-chart-line"></span></div>
                        <span class="kpi-label"><?php _e('Saldo Total (USD)', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-kpi-balance-usd">0</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Divisa Extranjera', 'aura-suite'); ?></div>
                    </div>
                </div>

                <!-- Filtros para cuentas -->
                <div class="aura-filters-bar">
                    <div class="aura-filters-group" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <div class="input-group" style="min-width: 220px; max-width: 320px;">
                            <span class="input-group-text">🔍</span>
                            <input type="text" id="aura-accounts-search" class="form-control" placeholder="<?php esc_attr_e('Buscar...', 'aura-suite'); ?>">
                        </div>
                        <select id="aura-filter-type" class="form-control" style="width: auto;">
                            <option value=""><?php _e('Todos los tipos', 'aura-suite'); ?></option>
                            <option value="bank_account"><?php _e('Banco', 'aura-suite'); ?></option>
                            <option value="petty_cash"><?php _e('Caja Chica', 'aura-suite'); ?></option>
                            <option value="contributions_fund"><?php _e('Fondo de Aportes', 'aura-suite'); ?></option>
                            <option value="usd_cash"><?php _e('Caja USD', 'aura-suite'); ?></option>
                            <option value="eur_cash"><?php _e('Caja EUR', 'aura-suite'); ?></option>
                            <option value="cad_cash"><?php _e('Caja CAD', 'aura-suite'); ?></option>
                            <option value="foreign_cash"><?php _e('Caja Divisa Extranjera', 'aura-suite'); ?></option>
                            <option value="custom"><?php _e('Personalizada', 'aura-suite'); ?></option>
                        </select>
                        <select id="aura-filter-currency" class="form-control" style="width: auto;">
                            <option value=""><?php _e('Todas las monedas', 'aura-suite'); ?></option>
                            <option value="MXN">MXN</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="CAD">CAD</option>
                        </select>
                        <select id="aura-filter-status" class="form-control" style="width: auto;">
                            <option value=""><?php _e('Cualquier estado', 'aura-suite'); ?></option>
                            <option value="1"><?php _e('Activa', 'aura-suite'); ?></option>
                            <option value="0"><?php _e('Inactiva', 'aura-suite'); ?></option>
                        </select>
                        <span id="aura-filter-count" class="badge badge-gray aura-hidden"></span>
                        <button type="button" id="aura-filter-reset" class="btn btn-secondary btn-sm aura-filter-reset-btn aura-hidden" aria-label="<?php esc_attr_e('Limpiar filtros', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php _e('Limpiar', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>

                <div class="aura-dt-wrapper">
                    <table class="dataTable display responsive nowrap aura-table-fullwidth" id="aura-accounts-table">
                        <thead>
                            <tr>
                                <th><span class="dashicons dashicons-bank" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Cuenta', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Nombre de la cuenta bancaria o caja y su entidad financiera.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                <th><span class="dashicons dashicons-category" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Tipo', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Clasificación operativa: Banco, Caja Menor, Tarjeta, etc.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                <th><span class="dashicons dashicons-money-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Moneda', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Moneda base de la cuenta (COP, USD, EUR, etc.).', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                <th><span class="dashicons dashicons-chart-area" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Saldo actual', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Saldo disponible consolidado y número enmascarado.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                <th><span class="dashicons dashicons-yes-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Estado', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Condición de operatividad: Activa o Inactiva.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                <th><span class="dashicons dashicons-admin-generic" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Acciones', 'aura-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Traspasos entre Cuentas -->
            <div id="tab-traspasos" class="aura-tab-panel">
                <div class="aura-card-head">
                    <div>
                        <h2 class="aura-title-with-help">
                            <?php _e('Traspasos entre Cuentas y Fondeo de Caja', 'aura-suite'); ?>
                            <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Transfiere fondos entre cuentas bancarias o fondea tu Caja Chica sin generar falsos gastos ni ingresos en el Estado de Resultados (P&L).', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                        </h2>
                        <p style="margin: 4px 0 0; color: var(--aura-text-muted, #64748b); font-size: 13px;">
                            <?php _e('Movimientos de capital entre cuentas propias: débito y crédito atómico con trazabilidad y soporte multimoneda.', 'aura-suite'); ?>
                        </p>
                    </div>
                    <div class="aura-section-actions">
                        <button type="button" class="btn btn-secondary btn-lift" id="aura-transfers-export-btn">
                            <span class="dashicons dashicons-download" style="vertical-align:middle;"></span>
                            <?php _e('Exportar CSV', 'aura-suite'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-transfer-new-btn" style="background:#0d9488;">
                            <span class="dashicons dashicons-randomize" style="vertical-align:middle;"></span>
                            <?php _e('Nuevo Traspaso', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>

                <!-- KPIs de Traspasos -->
                <div class="aura-report-kpis aura-kpis-top-spacer">
                    <div class="kpi-card kpi-indigo card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-randomize"></span></div>
                        <span class="kpi-label"><?php _e('Total Traspasos', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-kpi-transfer-count">0</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Operaciones registradas', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-emerald card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-money-alt"></span></div>
                        <span class="kpi-label"><?php _e('Volumen Transferido', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-kpi-transfer-volume" style="color:#10b981;">0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Total movilizado', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-blue card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-vault"></span></div>
                        <span class="kpi-label"><?php _e('Fondeos a Caja Chica', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-kpi-transfer-petty-cash" style="color:#0284c7;">0</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Aperturas / Recargas', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-amber card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-clock"></span></div>
                        <span class="kpi-label"><?php _e('Último Traspaso', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-kpi-transfer-last-date" style="font-size:1.1rem; color:#f59e0b;">—</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <span id="aura-kpi-transfer-last-folio"><?php _e('Sin movimientos', 'aura-suite'); ?></span></div>
                    </div>
                </div>

                <!-- Filtros para Traspasos -->
                <div class="aura-filters-bar" style="margin-top: 16px;">
                    <div class="aura-filters-group" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <div class="input-group" style="min-width: 240px;">
                            <span class="input-group-text">🔍</span>
                            <input type="text" id="aura-transfers-search" class="form-control" placeholder="<?php esc_attr_e('Buscar por folio, notas o referencia...', 'aura-suite'); ?>">
                        </div>
                        <select id="aura-filter-transfer-source" class="form-control" style="width:auto;">
                            <option value=""><?php _e('Todas las cuentas origen', 'aura-suite'); ?></option>
                        </select>
                        <select id="aura-filter-transfer-destination" class="form-control" style="width:auto;">
                            <option value=""><?php _e('Todas las cuentas destino', 'aura-suite'); ?></option>
                        </select>
                        <select id="aura-filter-transfer-status" class="form-control" style="width:auto;">
                            <option value=""><?php _e('Todos los estados', 'aura-suite'); ?></option>
                            <option value="completed"><?php _e('Completados', 'aura-suite'); ?></option>
                            <option value="cancelled"><?php _e('Anulados', 'aura-suite'); ?></option>
                        </select>
                        <button type="button" id="aura-transfers-refresh-btn" class="btn btn-secondary btn-lift" title="<?php esc_attr_e('Recargar historial', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                            <?php _e('Actualizar', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>

                <!-- Tabla de Historial de Traspasos -->
                <div class="aura-dt-wrapper">
                    <table class="dataTable display responsive nowrap aura-table-fullwidth" id="aura-transfers-table">
                        <thead>
                            <tr>
                                <th><span class="dashicons dashicons-tag" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Folio / Fecha', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-arrow-up-alt" style="vertical-align:middle;margin-right:4px;color:#ef4444;"></span><?php _e('Cuenta Origen (Salida)', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-money-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Monto Salida', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-arrow-down-alt" style="vertical-align:middle;margin-right:4px;color:#10b981;"></span><?php _e('Cuenta Destino (Entrada)', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-money-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Monto Entrada', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-admin-users" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Usuario', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-edit" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Referencia / Notas', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-flag" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Estado', 'aura-suite'); ?></th>
                                <th class="aura-actions-col" style="text-align: right; width: 110px;"><span class="dashicons dashicons-admin-generic" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Acciones', 'aura-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="aura-transfers-tbody">
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 20px; color: var(--aura-text-muted, #888);">
                                    <?php _e('Cargando historial de traspasos...', 'aura-suite'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Presupuestos -->
            <div id="tab-presupuestos" class="aura-tab-panel">
                <div class="aura-card-head">
                    <div>
                        <h2 class="aura-title-with-help">
                            <?php _e('Presupuestos Anuales y Mensuales', 'aura-suite'); ?>
                            <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Define topes anuales y mensuales de la organización. Estos valores alimentan los reportes de cierre y los indicadores del Dashboard Financiero.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                        </h2>
                    </div>
                    <div class="aura-section-actions">
                        <select id="aura-global-budget-year" class="form-control aura-input-year" style="width:auto; display:inline-block; font-weight:700;" title="<?php esc_attr_e('Seleccionar año fiscal', 'aura-suite'); ?>">
                            <?php for ($y = (int) current_time('Y') + 2; $y >= 2020; $y--) : ?>
                            <option value="<?php echo esc_attr($y); ?>" <?php selected($y, (int) current_time('Y')); ?>><?php echo esc_html($y); ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-budget-new-year-btn">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <span><?php _e('Nuevo Año', 'aura-suite'); ?></span>
                        </button>
                        <button type="button" class="btn btn-secondary btn-lift" id="aura-budget-open-inline-btn">
                            <span class="dashicons dashicons-edit"></span>
                            <span><?php _e('Configurar Año', 'aura-suite'); ?></span>
                        </button>
                        <a
                            href="<?php echo esc_url(admin_url('admin-ajax.php?action=aura_finance_budget_export_all&nonce=' . wp_create_nonce('aura_financial_accounts_nonce'))); ?>"
                            class="btn btn-secondary btn-lift"
                            id="aura-budget-export-all-btn"
                            title="<?php esc_attr_e('Descarga una copia de seguridad en CSV de todos los presupuestos de todos los años', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-download"></span>
                            <span><?php _e('Exportar / Backup (CSV)', 'aura-suite'); ?></span>
                        </a>
                        <button type="button" class="btn btn-secondary btn-lift" id="aura-budget-open-import-btn" title="<?php esc_attr_e('Importar presupuestos desde archivo CSV o Excel', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-upload"></span>
                            <span><?php _e('Importar Plantilla', 'aura-suite'); ?></span>
                        </button>
                        <button type="button" class="btn btn-danger btn-shimmer btn-lift" id="aura-budget-delete-year-btn" title="<?php esc_attr_e('Eliminar presupuesto del año seleccionado', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <span><?php _e('Eliminar Año', 'aura-suite'); ?></span>
                        </button>
                    </div>
                </div>

                <!-- KPIs de Presupuesto Anual -->
                <div class="aura-report-kpis aura-budget-overview">
                    <div class="kpi-card kpi-indigo card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
                        <span class="kpi-label"><?php _e('Tope anual', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-budget-kpi-annual">0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Año Fiscal', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-blue card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-calculator"></span></div>
                        <span class="kpi-label"><?php _e('Suma Mensual Asignada', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-budget-kpi-monthly">0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('12 Meses', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-rose card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-money-alt"></span></div>
                        <span class="kpi-label"><?php _e('Total Ejecutado Real', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-budget-kpi-spent">0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Aprobado', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-emerald card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-vault"></span></div>
                        <span class="kpi-label"><?php _e('Disponible restante', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-budget-kpi-remaining">0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Saldo Libre', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-amber card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-shield"></span></div>
                        <span class="kpi-label"><?php _e('Política Exceso', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-budget-kpi-policy">Advertir</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Regla de Control', 'aura-suite'); ?></div>
                    </div>
                </div>

                <!-- Barra de Progreso de Ejecución Anual -->
                <div class="aura-budget-progress-panel aura-budget-progress-panel--spaced">
                    <div class="aura-budget-progress-panel__head">
                        <span><?php _e('Ejecutado vs Tope Anual', 'aura-suite'); ?> (<span id="aura-budget-progress-year-tag"><?php echo esc_html(current_time('Y')); ?></span>)</span>
                        <strong id="aura-budget-progress-text">0%</strong>
                    </div>
                    <div class="aura-budget-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <span id="aura-budget-progress-bar" class="aura-budget-progress-fill aura-budget-progress-fill--default"></span>
                    </div>
                </div>

                <!-- Desglose Mensual (12 Meses) -->
                <div class="aura-budget-monthly-section" style="margin-top:20px;">
                    <div class="aura-card-head" style="margin-bottom:12px;">
                        <div>
                            <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--aura-text-primary, #0f172a);">
                                📅 <?php _e('Distribución y Ejecución Mensual', 'aura-suite'); ?> — <span id="aura-budget-active-year-label"><?php echo esc_html(current_time('Y')); ?></span>
                            </h3>
                            <p class="description" style="margin:2px 0 0;"><?php _e('Monitorea mes a mes el presupuesto asignado frente al gasto real aprobado en transacciones.', 'aura-suite'); ?></p>
                        </div>
                        <div class="aura-section-actions">
                            <button type="button" class="btn btn-secondary btn-lift" id="aura-budget-quick-distribute-btn">
                                <span class="dashicons dashicons-calculator"></span>
                                <span><?php _e('Distribuir Tope Parejo (÷12)', 'aura-suite'); ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="aura-dt-wrapper">
                        <table class="dataTable display responsive nowrap aura-table-fullwidth aura-budget-monthly-table" id="aura-budget-monthly-breakdown-table">
                            <thead>
                                <tr>
                                    <th><span class="dashicons dashicons-calendar-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Mes', 'aura-suite'); ?></th>
                                    <th><span class="dashicons dashicons-money-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Presupuesto Asignado', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Tope mensual presupuestado para gastos y egresos.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                    <th><span class="dashicons dashicons-chart-line" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Ejecutado Real', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Suma acumulada de gastos reales ejecutados en el mes.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                    <th><span class="dashicons dashicons-vault" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Disponible', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Margen remanente antes de alcanzar el tope mensual.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                    <th><span class="dashicons dashicons-performance" style="vertical-align:middle;margin-right:4px;"></span><?php _e('% Ejecución', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Porcentaje consumido con micro-barra de progreso semafórico.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                    <th><span class="dashicons dashicons-flag" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Estado', 'aura-suite'); ?></th>
                                    <th><span class="dashicons dashicons-admin-generic" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Acciones', 'aura-suite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="7" style="text-align:center; padding:20px; color:#64748b;"><?php _e('Cargando datos del presupuesto...', 'aura-suite'); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Historial de Años Presupuestales -->
                <div class="aura-budget-history-section" style="margin-top:25px;">
                    <div class="aura-card-head" style="margin-bottom:12px;">
                        <div>
                            <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">
                                🏛️ <?php _e('Historial de Años Presupuestales Registrados', 'aura-suite'); ?>
                            </h3>
                            <p class="description" style="margin:2px 0 0;"><?php _e('Resumen global de todos los presupuestos anuales configurados en el sistema.', 'aura-suite'); ?></p>
                        </div>
                    </div>

                    <div class="aura-dt-wrapper">
                        <table class="dataTable display responsive nowrap aura-table-fullwidth" id="aura-budget-configured-years-table">
                            <thead>
                                <tr>
                                    <th><span class="dashicons dashicons-calendar" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Año Fiscal', 'aura-suite'); ?></th>
                                    <th><span class="dashicons dashicons-money-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Tope Anual', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Límite máximo general para el ejercicio contable completo.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                    <th><span class="dashicons dashicons-calculator" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Suma Mensual', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Sumatoria de las asignaciones de los 12 meses del año.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                    <th><span class="dashicons dashicons-chart-pie" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Total Ejecutado', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Gasto consolidado devengado durante el año fiscal.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                    <th><span class="dashicons dashicons-shield" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Política de Exceso', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Comportamiento ante sobrecostos: Bloquear transacciones o solo advertir.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                    <th><span class="dashicons dashicons-yes-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Estado', 'aura-suite'); ?></th>
                                    <th><span class="dashicons dashicons-admin-generic" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Acciones', 'aura-suite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="7" style="text-align:center; padding:15px; color:#64748b;"><?php _e('Cargando historial de presupuestos...', 'aura-suite'); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB: Caja Chica -->
            <div id="tab-cajachica" class="aura-tab-panel">
                <div class="aura-card-head">
                    <div>
                        <h2 class="aura-title-with-help">
                            <?php _e('Caja Chica: Entregas y Rendiciones', 'aura-suite'); ?>
                            <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Control de fondos operativos para custodios internos y anticipos para diligencias/compras a terceros con liquidación de facturas.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                        </h2>
                        <p class="aura-section-subtitle" style="margin:4px 0 0;font-size:12.5px;color:var(--aura-text-muted, #64748b);">
                            <?php _e('Gestiona anticipos a terceros, fondos fijos de área, comprobación de facturas y reintegro de sobrantes en efectivo.', 'aura-suite'); ?>
                        </p>
                    </div>
                    <div class="aura-section-actions">
                        <button type="button" class="btn btn-secondary btn-lift" id="aura-petty-refresh-btn" title="<?php esc_attr_e('Actualizar registros', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-update" style="vertical-align:middle;font-size:16px;"></span>
                            <?php _e('Refrescar', 'aura-suite'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-petty-open-inline-btn">
                            <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-right:2px;"></span>
                            <?php _e('Nueva Entrega / Anticipo', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>

                <!-- Banner Informativo Contextual / Guía Rápida -->
                <div class="aura-petty-guide-banner" id="aura-petty-guide-banner">
                    <div class="aura-petty-guide-banner__icon">💡</div>
                    <div class="aura-petty-guide-banner__content">
                        <strong><?php _e('Tip Contable:', 'aura-suite'); ?></strong>
                        <span>
                            <?php _e('Usa', 'aura-suite'); ?> <strong style="color:var(--aura-info-text, #0284c7);"><?php _e('[Tercero]', 'aura-suite'); ?></strong> <?php _e('para anticipos temporales de diligencias que se rinden con facturas y vueltos. Usa', 'aura-suite'); ?> <strong style="color:var(--aura-text-muted, #475569);"><?php _e('[Interno]', 'aura-suite'); ?></strong> <?php _e('para custodios de área que ejecutan gastos directos contra el presupuesto.', 'aura-suite'); ?>
                        </span>
                    </div>
                    <button type="button" class="aura-petty-guide-banner__close" id="aura-petty-guide-close" title="<?php esc_attr_e('Cerrar aviso', 'aura-suite'); ?>">✕</button>
                </div>

                <!-- KPIs Ejecutivos de Caja Chica con Ayudas Contextuales Obligatorias -->
                <div class="aura-report-kpis aura-petty-kpis-grid">
                    <div class="kpi-card kpi-indigo card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-portfolio"></span></div>
                        <span class="kpi-label">
                            <?php _e('Total Entregado', 'aura-suite'); ?>
                            <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Monto total otorgado en efectivo para compras o fondos operativos en circulación.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                        </span>
                        <div class="kpi-value"><strong id="aura-kpi-petty-delivered">$0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Capital entregado', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-emerald card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-media-document"></span></div>
                        <span class="kpi-label">
                            <?php _e('Total Comprobado (Gastado)', 'aura-suite'); ?>
                            <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Suma total de compras justificadas mediante facturas y recibos tributarios válidos.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                        </span>
                        <div class="kpi-value"><strong id="aura-kpi-petty-spent">$0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Justificado', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-cyan card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-vault"></span></div>
                        <span class="kpi-label">
                            <?php _e('Efectivo Reintegrado', 'aura-suite'); ?>
                            <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Dinero físico sobrante de compras que ha sido devuelto a la caja de seguridad.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                        </span>
                        <div class="kpi-value"><strong id="aura-kpi-petty-returned">$0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Sobrante en caja', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-amber card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-clock"></span></div>
                        <span class="kpi-label">
                            <?php _e('Entregas Activas', 'aura-suite'); ?>
                            <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Cantidad de fondos en poder de custodios pendientes de rendición o pendientes de aprobación.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                        </span>
                        <div class="kpi-value"><strong id="aura-kpi-petty-active">0</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('En circulación', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-rose card-lift" id="aura-kpi-petty-overdue-box">
                        <div class="kpi-icon"><span class="dashicons dashicons-warning"></span></div>
                        <span class="kpi-label">
                            <?php _e('Entregas Vencidas', 'aura-suite'); ?>
                            <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Fondos que han superado su fecha límite de liquidación sin justificar facturas ni devolver el cambio.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                        </span>
                        <div class="kpi-value"><strong id="aura-kpi-petty-overdue">0</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Plazo superado', 'aura-suite'); ?></div>
                    </div>
                </div>

                <!-- Filtros Interactivos Compactos (Tablenav Horizontal Datatables) -->
                <div class="aura-filters-bar aura-petty-filters-bar">
                    <div class="aura-petty-filters-left">
                        <div class="input-group aura-petty-search-group" style="min-width: 260px;">
                            <span class="input-group-text" id="addon-petty-search">🔍</span>
                            <input type="text" id="aura-petty-search" class="form-control" placeholder="<?php esc_attr_e('Buscar responsable, caja, propósito...', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Buscar', 'aura-suite'); ?>" aria-describedby="addon-petty-search">
                        </div>

                        <div class="aura-input-group aura-petty-filter-item">
                            <span class="aura-input-group-text" id="addon-petty-custodian">
                                <span class="dashicons dashicons-admin-users"></span>
                            </span>
                            <select id="aura-petty-filter-custodian" class="aura-input" aria-describedby="addon-petty-custodian">
                                <option value=""><?php _e('Todos los Custodios', 'aura-suite'); ?></option>
                                <option value="third_party"><?php _e('Terceros (Diligencias)', 'aura-suite'); ?></option>
                                <option value="internal"><?php _e('Personal Interno', 'aura-suite'); ?></option>
                            </select>
                        </div>

                        <div class="aura-input-group aura-petty-filter-item">
                            <span class="aura-input-group-text" id="addon-petty-status">
                                <span class="dashicons dashicons-tag"></span>
                            </span>
                            <select id="aura-petty-filter-status" class="aura-input" aria-describedby="addon-petty-status">
                                <option value=""><?php _e('Todos los Estados', 'aura-suite'); ?></option>
                                <option value="open"><?php _e('Abierta (Pendiente)', 'aura-suite'); ?></option>
                                <option value="submitted"><?php _e('Rendida (Por Aprobar)', 'aura-suite'); ?></option>
                                <option value="approved"><?php _e('Aprobada (Paz y Salvo)', 'aura-suite'); ?></option>
                                <option value="closed"><?php _e('Cerrada', 'aura-suite'); ?></option>
                                <option value="rejected"><?php _e('Rechazada', 'aura-suite'); ?></option>
                            </select>
                        </div>

                        <div class="aura-input-group aura-petty-filter-item">
                            <span class="aura-input-group-text" id="addon-petty-delivery-type">
                                <span class="dashicons dashicons-portfolio"></span>
                            </span>
                            <select id="aura-petty-filter-delivery-type" class="aura-input" aria-describedby="addon-petty-delivery-type">
                                <option value=""><?php _e('Todos los Fondos', 'aura-suite'); ?></option>
                                <option value="purchase_errand"><?php _e('🛒 Compras / Diligencias', 'aura-suite'); ?></option>
                                <option value="program_budget"><?php _e('📦 Presupuestos de Programa', 'aura-suite'); ?></option>
                            </select>
                        </div>

                        <div class="aura-input-group aura-petty-filter-item">
                            <span class="aura-input-group-text" id="addon-petty-overdue">
                                <span class="dashicons dashicons-clock"></span>
                            </span>
                            <select id="aura-petty-filter-overdue" class="aura-input" aria-describedby="addon-petty-overdue">
                                <option value=""><?php _e('Todos los Plazos', 'aura-suite'); ?></option>
                                <option value="overdue"><?php _e('⚠️ Vencidas', 'aura-suite'); ?></option>
                                <option value="ontime"><?php _e('✓ En Plazo Vigente', 'aura-suite'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="aura-petty-filters-right">
                        <span id="aura-petty-filter-count" class="aura-filter-count aura-hidden"></span>
                        <button type="button" id="aura-petty-filter-reset" class="btn btn-secondary btn-sm aura-filter-reset-btn aura-hidden" aria-label="<?php esc_attr_e('Limpiar filtros', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php _e('Limpiar', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>

                <div class="aura-dt-wrapper aura-petty-table-wrapper">
                    <table class="dataTable display responsive nowrap aura-table-fullwidth aura-table-modern" id="aura-petty-cash-table">
                        <thead>
                            <tr>
                                <th class="column-toggle" style="width:50px;text-align:center;">
                                    <span class="dashicons dashicons-editor-ol" style="vertical-align:middle;"></span> #
                                    <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Identificador y expansor de detalles completos en dispositivos móviles.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                                </th>
                                <th class="column-date">
                                    <span class="dashicons dashicons-calendar-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Fecha', 'aura-suite'); ?>
                                    <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Fecha y hora en que se entregó el fondo.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                                </th>
                                <th class="column-due aura-col-desktop">
                                    <span class="dashicons dashicons-clock" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Vence', 'aura-suite'); ?>
                                    <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Fecha límite para rendir cuentas con facturas y devolver el cambio.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                                </th>
                                <th class="column-account aura-col-desktop">
                                    <span class="dashicons dashicons-vault" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Cuenta Caja', 'aura-suite'); ?>
                                    <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Caja chica o cuenta puente de custodia donde radica el dinero.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                                </th>
                                <th class="column-responsible">
                                    <span class="dashicons dashicons-businessman" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Responsable / Tercero', 'aura-suite'); ?>
                                    <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Custodio del dinero: [Tercero] para compras/diligencias, [Interno] para responsable de área.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                                </th>
                                <th class="column-purpose aura-col-desktop">
                                    <span class="dashicons dashicons-location-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Propósito / Diligencia', 'aura-suite'); ?>
                                    <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Objetivo o misión para la cual se autorizó la entrega de efectivo.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                                </th>
                                <th class="column-delivered" style="text-align:right;">
                                    <span class="dashicons dashicons-arrow-down-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Entregado', 'aura-suite'); ?>
                                    <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Monto total inicial otorgado al custodio o tercero.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                                </th>
                                <th class="column-spent aura-col-desktop" style="text-align:right;">
                                    <span class="dashicons dashicons-cart" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Gastado', 'aura-suite'); ?>
                                    <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Total de compras respaldadas con facturas o recibos cargados.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                                </th>
                                <th class="column-returned aura-col-desktop" style="text-align:right;">
                                    <span class="dashicons dashicons-undo" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Devuelto', 'aura-suite'); ?>
                                    <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Efectivo sobrante reintegrado físicamente a la caja.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                                </th>
                                <th class="column-status" style="text-align:center;">
                                    <span class="dashicons dashicons-flag" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Estado', 'aura-suite'); ?>
                                    <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Fase contable: Abierta, Rendida (por aprobar), Aprobada (paz y salvo) o Cerrada.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                                </th>
                                <th class="column-actions aura-col-desktop" style="text-align:center;">
                                    <span class="dashicons dashicons-admin-generic" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Acciones', 'aura-suite'); ?>
                                    <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Acciones disponibles: Rendir facturas, ver comprobantes, aprobar, rechazar o eliminar.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                                </th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Reembolsos -->
            <div id="tab-reembolsos" class="aura-tab-panel">
                <div class="aura-card-head">
                    <div>
                        <h2 class="aura-title-with-help"><?php _e('Reembolsos a Personas', 'aura-suite'); ?></h2>
                    </div>
                    <div class="aura-section-actions">
                        <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-reimburse-open-inline-btn">
                            <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-right:2px;"></span>
                            <?php _e('Registrar deuda', 'aura-suite'); ?>
                        </button>
                        <button type="button" class="btn btn-indigo btn-lift" id="aura-reimburse-pay-open-inline-btn">
                            <span class="dashicons dashicons-money-alt" style="vertical-align:middle;margin-right:2px;"></span>
                            <?php _e('Registrar pago', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>
                <div class="aura-dt-wrapper">
                    <table class="dataTable display responsive nowrap aura-table-fullwidth" id="aura-reimbursements-table">
                        <thead>
                            <tr>
                                <th><span class="dashicons dashicons-calendar-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Fecha', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-businessman" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Tercero', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Beneficiario a quien se adeuda el reembolso de fondos.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                <th><span class="dashicons dashicons-tag" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Origen', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Movimiento de origen o registro manual del adeudo.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                <th><span class="dashicons dashicons-money-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Adeudado', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Monto total inicialmente reconocido para reembolso.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                <th><span class="dashicons dashicons-yes-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Pagado', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Monto amortizado o pagado efectivamente al beneficiario.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                <th><span class="dashicons dashicons-clock" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Pendiente', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Saldo remanente pendiente de liquidación.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                <th><span class="dashicons dashicons-flag" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Estado', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-admin-generic" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Acciones', 'aura-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Reportes y Cierre -->
            <div id="tab-reportes" class="aura-tab-panel">
                <div class="aura-card-head aura-card-head--reports">
                    <div>
                        <h2 class="aura-title-with-help"><?php _e('Reportería y Cierre', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Consolida flujo, presupuesto y auditoría. Cambia el año para analizar periodos anteriores.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h2>
                        <p><?php _e('Consolida flujo por cuenta, saldos por moneda/tipo, bloque Excel, ejecución presupuestal y auditoría cruzada.', 'aura-suite'); ?></p>
                    </div>
                    <div class="aura-report-actions" style="display:flex; align-items:center; gap:8px;">
                        <input type="number" id="aura-report-year" class="form-control aura-report-year-input" min="2000" max="2100" value="<?php echo esc_attr((int) current_time('Y')); ?>" style="width:110px; font-weight:700;">
                        <button type="button" class="btn btn-secondary btn-lift" id="aura-report-refresh-btn">
                            <span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
                            <?php _e('Actualizar reportes', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>

                <div class="aura-report-kpis">
                    <div class="kpi-card kpi-emerald card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-arrow-down-alt"></span></div>
                        <span class="kpi-label"><?php _e('Entradas por cuenta', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-report-kpi-inflows">0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Ingresos', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-rose card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-arrow-up-alt"></span></div>
                        <span class="kpi-label"><?php _e('Salidas por cuenta', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-report-kpi-outflows">0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Egresos', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-indigo card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-chart-pie"></span></div>
                        <span class="kpi-label"><?php _e('Ejecutado anual', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-report-kpi-budget">0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Presupuesto', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-amber card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-visibility"></span></div>
                        <span class="kpi-label"><?php _e('Hallazgos auditoría', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-report-kpi-audit">0</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Alertas', 'aura-suite'); ?></div>
                    </div>
                </div>

                <div class="aura-report-grid">
                    <section class="aura-report-panel">
                        <h3><?php _e('Flujo por cuenta', 'aura-suite'); ?></h3>
                        <div class="aura-report-table-wrap">
                            <table class="widefat striped" id="aura-report-accounts-table">
                                <thead>
                                    <tr>
                                        <th><span class="dashicons dashicons-bank" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Cuenta', 'aura-suite'); ?></th>
                                        <th><span class="dashicons dashicons-money-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Moneda', 'aura-suite'); ?></th>
                                        <th><span class="dashicons dashicons-arrow-down-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Entradas', 'aura-suite'); ?></th>
                                        <th><span class="dashicons dashicons-arrow-up-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Salidas', 'aura-suite'); ?></th>
                                        <th><span class="dashicons dashicons-vault" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Saldo', 'aura-suite'); ?></th>
                                    </tr>
                                </thead>
                                <tbody><tr><td colspan="5"><?php _e('Cargando reporte...', 'aura-suite'); ?></td></tr></tbody>
                            </table>
                        </div>
                    </section>

                    <section class="aura-report-panel">
                        <h3><?php _e('Saldos por moneda y tipo', 'aura-suite'); ?></h3>
                        <div class="aura-report-dual">
                            <div>
                                <h4><?php _e('Monedas', 'aura-suite'); ?></h4>
                                <div class="aura-report-table-wrap">
                                    <table class="widefat striped" id="aura-report-currency-table">
                                        <thead><tr><th><span class="dashicons dashicons-money-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Moneda', 'aura-suite'); ?></th><th><span class="dashicons dashicons-portfolio" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Cuentas', 'aura-suite'); ?></th><th><span class="dashicons dashicons-vault" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Saldo', 'aura-suite'); ?></th></tr></thead>
                                        <tbody><tr><td colspan="3"><?php _e('Cargando...', 'aura-suite'); ?></td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                            <div>
                                <h4><?php _e('Tipos de cuenta', 'aura-suite'); ?></h4>
                                <div class="aura-report-table-wrap">
                                    <table class="widefat striped" id="aura-report-type-table">
                                        <thead><tr><th><span class="dashicons dashicons-category" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Tipo', 'aura-suite'); ?></th><th><span class="dashicons dashicons-portfolio" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Cuentas', 'aura-suite'); ?></th><th><span class="dashicons dashicons-vault" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Saldo', 'aura-suite'); ?></th></tr></thead>
                                        <tbody><tr><td colspan="3"><?php _e('Cargando...', 'aura-suite'); ?></td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="aura-report-panel aura-report-panel--full">
                        <h3><?php _e('Presupuesto: anual vs mensual', 'aura-suite'); ?></h3>
                        <div id="aura-inline-budget-actions" class="aura-inline-actions-row">
                            <button type="button" class="btn btn-secondary btn-lift" id="aura-inline-budget-edit-btn"><?php _e('Editar límites', 'aura-suite'); ?></button>
                            <button type="button" class="btn btn-primary btn-shimmer btn-lift aura-hidden" id="aura-inline-budget-save-btn"><?php _e('Guardar Cambios', 'aura-suite'); ?></button>
                        </div>
                        <div class="aura-report-budget-summary" id="aura-report-budget-summary"></div>
                        <div class="aura-report-table-wrap">
                            <table class="widefat striped" id="aura-report-budget-table">
                                <thead>
                                    <tr>
                                        <th><span class="dashicons dashicons-calendar-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Mes', 'aura-suite'); ?></th>
                                        <th><span class="dashicons dashicons-money-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Límite', 'aura-suite'); ?></th>
                                        <th><span class="dashicons dashicons-chart-line" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Ejecutado', 'aura-suite'); ?></th>
                                        <th><span class="dashicons dashicons-vault" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Disponible', 'aura-suite'); ?></th>
                                        <th><span class="dashicons dashicons-performance" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Progreso', 'aura-suite'); ?></th>
                                    </tr>
                                </thead>
                                <tbody><tr><td colspan="5"><?php _e('Cargando...', 'aura-suite'); ?></td></tr></tbody>
                            </table>
                        </div>
                    </section>

                    <section class="aura-report-panel aura-report-panel--audit">
                        <h3><?php _e('Auditoría cruzada', 'aura-suite'); ?></h3>
                        <div id="aura-report-audit-list" class="aura-report-audit-list"></div>
                    </section>
                </div>
            </div>

            <!-- TAB: Cambio de Divisas y Auditoría -->
            <div id="tab-divisas" class="aura-tab-panel">
                <div class="aura-card-head">
                    <div>
                        <h2 class="aura-title-with-help">
                            <?php _e('Historial y Auditoría de Cambio de Divisas', 'aura-suite'); ?>
                            <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Monitorea todas las operaciones de compra y venta de divisas (USD / Moneda Local), consulta tasas promedio ponderadas y verifica la trazabilidad contable y de auditoría.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button>
                        </h2>
                        <p style="margin: 4px 0 0; color: var(--aura-text-muted, #64748b); font-size: 13px;">
                            <?php _e('Registro inmutable de conversiones entre cuentas bancarias y cajas de la empresa.', 'aura-suite'); ?>
                        </p>
                    </div>
                    <div class="aura-section-actions">
                        <button type="button" class="btn btn-secondary btn-lift" id="aura-exchanges-export-btn">
                            <span class="dashicons dashicons-download" style="vertical-align:middle;"></span>
                            <?php _e('Exportar CSV', 'aura-suite'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-tab-exchange-new-btn">
                            <span class="dashicons dashicons-money-alt" style="vertical-align:middle;"></span>
                            <?php _e('Nuevo Cambio de Divisa', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>

                <!-- KPIs de Cambio de Divisas (prompt-maestro.md) -->
                <div class="aura-report-kpis aura-kpis-top-spacer">
                    <div class="kpi-card kpi-indigo card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-money-alt"></span></div>
                        <span class="kpi-label"><?php _e('Total Operaciones', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-kpi-exchange-total">0</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Cambios ejecutados', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-emerald card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-arrow-down-alt"></span></div>
                        <span class="kpi-label"><?php _e('Total Divisa Comprada', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-kpi-exchange-bought" style="color: #10b981;">0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Local ➔ Extranjera', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-blue card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-arrow-up-alt"></span></div>
                        <span class="kpi-label"><?php _e('Total Divisa Vendida', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-kpi-exchange-sold" style="color: #6366f1;">0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Extranjera ➔ Local', 'aura-suite'); ?></div>
                    </div>
                    <div class="kpi-card kpi-amber card-lift">
                        <div class="kpi-icon"><span class="dashicons dashicons-chart-line"></span></div>
                        <span class="kpi-label"><?php _e('Tasa Promedio', 'aura-suite'); ?></span>
                        <div class="kpi-value"><strong id="aura-kpi-exchange-avg-rate">0.00</strong></div>
                        <div class="kpi-change"><span class="pulse-dot"></span> <?php _e('Tasa ponderada', 'aura-suite'); ?></div>
                    </div>
                </div>

                <!-- Filtros para Auditoría de Divisas -->
                <div class="aura-filters-bar">
                    <div class="aura-filters-group" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <div class="input-group" style="min-width: 240px;">
                            <span class="input-group-text">🔍</span>
                            <input type="text" id="aura-exchanges-search" class="form-control" placeholder="<?php esc_attr_e('Buscar por cuenta, notas o auditor...', 'aura-suite'); ?>">
                        </div>
                        <select id="aura-filter-exchange-currency" class="form-control" style="width:auto;">
                            <option value=""><?php _e('Todas las divisas', 'aura-suite'); ?></option>
                            <option value="USD">USD ($)</option>
                            <option value="EUR">EUR (€)</option>
                            <option value="CAD">CAD (C$)</option>
                        </select>
                        <select id="aura-filter-exchange-dir" class="form-control" style="width:auto;">
                            <option value=""><?php _e('Todas las operaciones', 'aura-suite'); ?></option>
                            <option value="buy"><?php _e('Compras (Local ➔ Divisa)', 'aura-suite'); ?></option>
                            <option value="sell"><?php _e('Ventas (Divisa ➔ Local)', 'aura-suite'); ?></option>
                        </select>
                        <select id="aura-filter-exchange-status" class="form-control" style="width:auto;">
                            <option value=""><?php _e('Todos los estados', 'aura-suite'); ?></option>
                            <option value="completed"><?php _e('Completadas', 'aura-suite'); ?></option>
                            <option value="reverted"><?php _e('Revertidas / Anuladas', 'aura-suite'); ?></option>
                        </select>
                        <button type="button" id="aura-exchanges-refresh-btn" class="btn btn-secondary btn-lift" title="<?php esc_attr_e('Recargar historial', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                            <?php _e('Actualizar', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>

                <!-- Tabla de Auditoría de Divisas -->
                <div class="aura-dt-wrapper">
                    <table class="dataTable display responsive nowrap aura-table-fullwidth" id="aura-exchanges-table">
                        <thead>
                            <tr>
                                <th><span class="dashicons dashicons-clock" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Fecha / Hora', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-randomize" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Operación', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Sentido de la conversión: Compra de divisa extranjera o Venta hacia moneda local.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                <th><span class="dashicons dashicons-arrow-up-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Cuenta Origen (Salida)', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-chart-line" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Tasa Cambio', 'aura-suite'); ?> <button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Tasa de cambio pactada para la conversión entre divisas.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></th>
                                <th><span class="dashicons dashicons-arrow-down-alt" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Cuenta Destino (Entrada)', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-admin-users" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Auditor / Usuario', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-edit" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Notas / Referencia', 'aura-suite'); ?></th>
                                <th><span class="dashicons dashicons-flag" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Estado', 'aura-suite'); ?></th>
                                <th class="aura-actions-col" style="text-align: right; width: 120px;"><span class="dashicons dashicons-admin-generic" style="vertical-align:middle;margin-right:4px;"></span><?php _e('Acciones', 'aura-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="aura-exchanges-tbody">
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 20px; color: var(--aura-text-muted, #888);">
                                    <?php _e('Cargando registros de cambio de divisa...', 'aura-suite'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- ── FOOTER CANÓNICO GLOBAL ──────────────────────────────── -->
                <div class="adp-footer" style="margin-top: 24px;">
                    <div>
                        Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg> Diego Giraldo</a></strong> &bull; Versión <?php echo esc_html( defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0' ); ?> &bull; Aura Business Suite &copy; <?php echo date('Y'); ?>
                    </div>
                </div>
        </main>
        </div><!-- .aura-layout -->

<div id="aura-finance-budget-modal" class="aura-modal-overlay" aria-hidden="true">
    <div class="aura-modal-content aura-finance-modal__dialog--large" role="dialog" aria-modal="true" aria-labelledby="aura-budget-modal-title" style="max-width: 880px;">
        <div class="aura-modal-header">
            <div>
                <h2 id="aura-budget-modal-title" class="aura-title-with-help">
                    <span class="dashicons dashicons-calculator" style="margin-right:6px; color:#2563eb;"></span>
                    <?php _e('Presupuesto Anual y Mensual', 'aura-suite'); ?>
                </h2>
                <p class="description" style="margin:2px 0 0; font-size:13px;">
                    <?php _e('Configura el presupuesto total para el año fiscal o define los montos mes a mes.', 'aura-suite'); ?>
                </p>
            </div>
            <button type="button" class="aura-modal-close" data-modal-close="aura-finance-budget-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
        </div>

        <form id="aura-budget-form" class="aura-modal-body" style="padding-top:16px;">
            <!-- Fila Superior: Año y Política de Exceso -->
            <div class="aura-budget-top-row" style="display:grid; grid-template-columns: 1fr 1.5fr; gap:16px; margin-bottom:18px;">
                <div class="aura-field">
                    <label for="aura-budget-year" class="aura-label" style="font-weight:600; color:#0f172a; margin-bottom:6px; display:block;">
                        📅 <?php _e('Año Fiscal', 'aura-suite'); ?>
                    </label>
                    <input type="number" class="aura-input" name="year" id="aura-budget-year" min="2020" max="2100" value="<?php echo esc_attr((int) current_time('Y')); ?>" required style="font-size:15px; font-weight:700; height:40px;">
                </div>

                <div class="aura-field">
                    <label for="aura-budget-policy" class="aura-label" style="font-weight:600; color:#0f172a; margin-bottom:6px; display:block;">
                        🛡️ <?php _e('Política al Superar Presupuesto', 'aura-suite'); ?>
                    </label>
                    <select name="exceed_policy" id="aura-budget-policy" class="aura-input" style="height:40px; font-size:13px;">
                        <option value="warn"><?php _e('Solo advertir (Permitir registrar gastos)', 'aura-suite'); ?></option>
                        <option value="block"><?php _e('Bloquear creación de gastos al exceder', 'aura-suite'); ?></option>
                    </select>
                </div>
            </div>

            <!-- Selector de Modo de Configuración (Segmented Cards) -->
            <div class="aura-budget-mode-selector-wrap" style="margin-bottom:18px;">
                <label class="aura-label" style="font-weight:700; font-size:13px; color:#1e293b; margin-bottom:8px; display:block;">
                    ⚙️ <?php _e('¿Cómo deseas configurar el presupuesto?', 'aura-suite'); ?>
                </label>
                <div class="aura-budget-mode-cards" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <!-- Opción 1: Dividir en 12 -->
                    <label class="aura-budget-mode-card is-active" id="aura-budget-mode-card-even" style="border:2px solid #2563eb; background:#eff6ff; border-radius:10px; padding:12px 14px; cursor:pointer; display:flex; align-items:flex-start; gap:10px; transition:all .2s ease;">
                        <input type="radio" name="budget_config_mode" value="even" checked style="margin-top:3px;">
                        <div>
                            <strong style="display:block; font-size:14px; color:#1e3a8a;">⚡ <?php _e('Opción 1: Presupuesto Anual (Dividir en 12)', 'aura-suite'); ?></strong>
                            <span style="font-size:12px; color:#3b82f6; display:block; margin-top:2px;">
                                <?php _e('Ingresas el tope total del año y el sistema lo distribuye en partes iguales mes a mes.', 'aura-suite'); ?>
                            </span>
                        </div>
                    </label>

                    <!-- Opción 2: Manual mes a mes -->
                    <label class="aura-budget-mode-card" id="aura-budget-mode-card-manual" style="border:2px solid #e2e8f0; background:#f8fafc; border-radius:10px; padding:12px 14px; cursor:pointer; display:flex; align-items:flex-start; gap:10px; transition:all .2s ease;">
                        <input type="radio" name="budget_config_mode" value="manual" style="margin-top:3px;">
                        <div>
                            <strong style="display:block; font-size:14px; color:#334155;">✍️ <?php _e('Opción 2: Configurar Mes a Mes (Suma Automática)', 'aura-suite'); ?></strong>
                            <span style="font-size:12px; color:#64748b; display:block; margin-top:2px;">
                                <?php _e('Ingresas el valor para cada mes y el sistema suma automáticamente el total anual.', 'aura-suite'); ?>
                            </span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Panel de Tope Anual (Con botón de cálculo / distribución) -->
            <div class="aura-budget-annual-panel" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:10px; padding:14px 18px; margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div style="flex:1; min-width:240px;">
                        <label for="aura-budget-annual-limit" class="aura-label" style="font-weight:700; color:#0f172a; margin-bottom:4px; display:block; font-size:13px;">
                            💰 <?php _e('Presupuesto Anual Total ($)', 'aura-suite'); ?>
                        </label>
                        <div style="position:relative;">
                            <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:16px; font-weight:700; color:#64748b;">$</span>
                            <input type="number" class="aura-input" step="0.01" min="0" name="annual_limit" id="aura-budget-annual-limit" value="0" required style="font-size:18px; font-weight:800; color:#0f172a; height:44px; padding-left:28px; background:#fff;">
                        </div>
                    </div>
                    <div id="aura-budget-even-tools" style="display:flex; align-items:flex-end; gap:8px; align-self:flex-end;">
                        <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-budget-apply-even-btn" style="height:40px; padding:0 18px; font-weight:600;">
                            <span class="dashicons dashicons-update"></span>
                            <span><?php _e('Dividir Tope en 12 Meses', 'aura-suite'); ?></span>
                        </button>
                    </div>
                    <div id="aura-budget-manual-tools" class="aura-hidden" style="display:flex; align-items:flex-end; gap:8px; align-self:flex-end;">
                        <button type="button" class="btn btn-secondary btn-lift" id="aura-budget-sum-to-annual-btn" style="height:40px; padding:0 14px; font-weight:600;" title="<?php esc_attr_e('Calcula la suma de los 12 meses y actualiza el tope anual', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-calculator"></span>
                            <span><?php _e('Actualizar Tope desde Meses', 'aura-suite'); ?></span>
                        </button>
                        <button type="button" class="btn btn-danger btn-shimmer btn-lift" id="aura-budget-clear-all-btn" style="height:40px; padding:0 12px;" title="<?php esc_attr_e('Poner 0 en todos los meses', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <span><?php _e('Poner en Cero', 'aura-suite'); ?></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Desglose de los 12 Meses (Mes a Mes) -->
            <div class="aura-budget-months-section" style="margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h3 style="margin:0; font-size:14px; font-weight:700; color:var(--aura-text-primary, #0f172a);">
                        🗓️ <?php _e('Asignación Mes a Mes (Enero — Diciembre)', 'aura-suite'); ?>
                    </h3>
                    <span id="aura-budget-monthly-hint" style="font-size:12px; color:var(--aura-text-muted, #64748b);">
                        <?php _e('Edita cualquier mes directamente para ajustar montos específicos.', 'aura-suite'); ?>
                    </span>
                </div>

                <div class="aura-budget-months-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap:10px;">
                    <?php
                    $month_labels = array(
                        1 => __('Enero', 'aura-suite'), 2 => __('Febrero', 'aura-suite'), 3 => __('Marzo', 'aura-suite'),
                        4 => __('Abril', 'aura-suite'), 5 => __('Mayo', 'aura-suite'), 6 => __('Junio', 'aura-suite'),
                        7 => __('Julio', 'aura-suite'), 8 => __('Agosto', 'aura-suite'), 9 => __('Septiembre', 'aura-suite'),
                        10 => __('Octubre', 'aura-suite'), 11 => __('Noviembre', 'aura-suite'), 12 => __('Diciembre', 'aura-suite')
                    );
                    foreach ($month_labels as $m_num => $m_name) :
                    ?>
                    <div class="aura-budget-month-card" style="background:var(--aura-card-bg, #fff); border:1px solid var(--aura-border, #e2e8f0); border-radius:8px; padding:8px 10px; box-shadow:0 1px 2px rgba(0,0,0,0.03);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <label for="aura-budget-month-<?php echo esc_attr($m_num); ?>" style="font-weight:700; font-size:12px; color:var(--aura-text-primary, #334155);">
                                <?php echo esc_html($m_name); ?>
                            </label>
                            <span class="aura-budget-month-idx" style="font-size:10px; color:var(--aura-text-muted, #94a3b8); font-weight:600;">M<?php echo esc_html($m_num); ?></span>
                        </div>
                        <div style="position:relative;">
                            <span style="position:absolute; left:8px; top:50%; transform:translateY(-50%); font-size:13px; font-weight:600; color:var(--aura-text-muted, #94a3b8);">$</span>
                            <input
                                type="number"
                                id="aura-budget-month-<?php echo esc_attr($m_num); ?>"
                                class="aura-budget-month-input aura-input"
                                min="0"
                                step="0.01"
                                value="0"
                                data-month="<?php echo esc_attr($m_num); ?>"
                                style="font-size:14px; font-weight:600; height:34px; padding-left:20px; width:100%; border-radius:6px;">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Resumen de Cuadre en Vivo -->
            <div class="aura-budget-summary-box" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; background:var(--aura-soft, #f8fafc); border:1px solid var(--aura-border, #e2e8f0); border-radius:10px; padding:12px 16px; margin-bottom:20px;">
                <div style="text-align:left;">
                    <span style="font-size:11px; font-weight:600; color:var(--aura-text-muted, #64748b); text-transform:uppercase; display:block;"><?php _e('Presupuesto Anual', 'aura-suite'); ?></span>
                    <strong id="aura-budget-modal-annual-ref" style="font-size:16px; color:var(--aura-text-primary, #0f172a); display:block; margin-top:2px;">$0.00</strong>
                </div>
                <div style="text-align:center;">
                    <span style="font-size:11px; font-weight:600; color:var(--aura-text-muted, #64748b); text-transform:uppercase; display:block;"><?php _e('Suma 12 Meses', 'aura-suite'); ?></span>
                    <strong id="aura-budget-monthly-total" style="font-size:16px; color:var(--aura-text-primary, #0f172a); display:block; margin-top:2px;">$0.00</strong>
                </div>
                <div style="text-align:right;">
                    <span style="font-size:11px; font-weight:600; color:var(--aura-text-muted, #64748b); text-transform:uppercase; display:block;"><?php _e('Estado / Diferencia', 'aura-suite'); ?></span>
                    <strong id="aura-budget-modal-diff" style="font-size:14px; color:#10b981; display:block; margin-top:2px;">$0.00 (Cuadrado exacto)</strong>
                </div>
            </div>

            <!-- Acordeón Desplegable para Importación desde Excel/CSV (Opcional / Copia de Seguridad) -->
            <details id="aura-budget-import-details-accordion" class="aura-budget-import-details" style="background:var(--aura-card-bg, #fff); border:1px dashed var(--aura-border, #cbd5e1); border-radius:8px; padding:12px 16px; margin-bottom:20px;">
                <summary style="font-size:13px; font-weight:700; color:var(--aura-text-primary, #334155); cursor:pointer; user-select:none; display:flex; justify-content:space-between; align-items:center;">
                    <span>📂 <?php _e('Importar Plantilla CSV / XLSX o Copia de Seguridad', 'aura-suite'); ?></span>
                    <span style="font-size:11px; font-weight:normal; color:var(--aura-text-muted, #64748b);"><?php _e('year,annual_limit,exceed_policy,jan..dec', 'aura-suite'); ?></span>
                </summary>
                <div style="margin-top:12px; padding-top:12px; border-top:1px solid var(--aura-border, #f1f5f9);">
                    <p class="description" style="margin:0 0 12px; font-size:12px; color:var(--aura-text-muted, #475569);">
                        <?php _e('Puedes descargar la plantilla oficial con el formato exacto o una copia de seguridad completa con todos los años para modificarlos o importarlos masivamente.', 'aura-suite'); ?>
                    </p>
                    <div class="aura-budget-import-actions" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <a
                            href="<?php echo esc_url(admin_url('admin-ajax.php?action=aura_finance_budget_template&nonce=' . wp_create_nonce('aura_financial_accounts_nonce'))); ?>"
                            class="btn btn-secondary btn-lift"
                            id="aura-budget-download-template"
                            title="<?php esc_attr_e('Descargar plantilla vacía con formato exacto', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-media-spreadsheet"></span>
                            <span><?php _e('Descargar Plantilla CSV', 'aura-suite'); ?></span>
                        </a>
                        <a
                            href="<?php echo esc_url(admin_url('admin-ajax.php?action=aura_finance_budget_export_all&nonce=' . wp_create_nonce('aura_financial_accounts_nonce'))); ?>"
                            class="btn btn-secondary btn-lift"
                            id="aura-budget-modal-backup-btn"
                            title="<?php esc_attr_e('Descarga copia de seguridad de todos los años configurados', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-download"></span>
                            <span><?php _e('Exportar Copia de Seguridad', 'aura-suite'); ?></span>
                        </a>
                        <div style="display:flex; align-items:center; gap:8px; margin-left:auto;">
                            <input type="file" id="aura-budget-import-file" accept=".csv,.xlsx" style="font-size:12px;">
                            <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-budget-import-btn">
                                <span class="dashicons dashicons-upload"></span>
                                <span><?php _e('Analizar e Importar', 'aura-suite'); ?></span>
                            </button>
                        </div>
                    </div>

                    <div id="aura-budget-import-wizard" class="aura-hidden aura-mt-10">
                        <div id="aura-budget-import-summary" class="aura-budget-import-summary"></div>
                        <div class="aura-budget-mapping-wrap">
                            <h4><?php _e('Mapeo de columnas', 'aura-suite'); ?></h4>
                            <div class="aura-budget-mapping-grid" id="aura-budget-mapping-grid"></div>
                        </div>
                        <div class="aura-budget-import-preview-wrap">
                            <h4><?php _e('Vista previa', 'aura-suite'); ?></h4>
                            <div class="aura-dt-wrapper">
                                <table class="dataTable display responsive nowrap aura-table-fullwidth" id="aura-budget-preview-table">
                                    <thead></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="aura-budget-import-wizard-actions" style="margin-top:10px; display:flex; gap:8px;">
                            <button type="button" class="btn btn-secondary btn-lift" id="aura-budget-validate-btn"><?php _e('Validar datos', 'aura-suite'); ?></button>
                            <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-budget-confirm-btn" disabled><?php _e('Confirmar importación', 'aura-suite'); ?></button>
                        </div>
                        <div id="aura-budget-import-validation" class="aura-budget-import-validation aura-hidden"></div>
                    </div>
                </div>
            </details>

            <!-- Acciones Finales del Modal -->
            <div class="aura-modal-actions" style="display:flex; justify-content:flex-end; gap:10px; padding-top:12px; border-top:1px solid var(--aura-border, #e2e8f0);">
                <button type="button" class="btn btn-secondary btn-lift" data-modal-close="aura-finance-budget-modal"><?php _e('Cancelar', 'aura-suite'); ?></button>
                <button type="submit" class="btn btn-primary btn-shimmer btn-lift" id="aura-budget-save-btn" style="height:38px; padding:0 22px; font-weight:700; font-size:14px;">
                    <span class="dashicons dashicons-saved" style="margin-right:4px; vertical-align:text-bottom;"></span>
                    <?php _e('Guardar Presupuesto', 'aura-suite'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
<div id="aura-finance-petty-modal" class="aura-finance-modal" style="display:none;" aria-hidden="true">
    <div class="aura-finance-modal__backdrop" data-modal-close="aura-finance-petty-modal"></div>
    <div class="aura-finance-modal__dialog aura-finance-modal__dialog--large" role="dialog" aria-modal="true" aria-labelledby="aura-petty-modal-title">
        <div class="aura-finance-modal__head">
            <div>
                <h2 id="aura-petty-modal-title" style="display:flex;align-items:center;gap:8px;">
                    <span class="dashicons dashicons-money-alt" style="color:var(--aura-primary,#0284c7);"></span>
                    <?php _e('Caja Chica: Fondos y Rendiciones', 'aura-suite'); ?>
                </h2>
                <p class="description" id="aura-petty-modal-subtitle"><?php _e('Entrega de fondos operativos para custodios internos, anticipos a terceros y comprobación de gastos.', 'aura-suite'); ?></p>
            </div>
            <button type="button" class="aura-modal-close" data-modal-close="aura-finance-petty-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
        </div>

        <input type="hidden" id="aura-petty-id" value="0">

        <?php
        $petty_drive_ready = class_exists('Aura_Drive_Manager') && (new Aura_Drive_Manager())->is_ready();
        ?>
        <!-- Stepper / Tabs de navegación de Alta Fidelidad -->
        <div class="aura-modal-wizard__steps aura-petty-tabs" style="margin-bottom:18px;display:flex;gap:10px;">
            <button type="button" class="aura-modal-step is-active aura-petty-tab" data-tab="delivery" id="aura-petty-tab-delivery" style="cursor:pointer;flex:1;justify-content:center;">
                <span class="dashicons dashicons-money-alt" style="margin-right:4px;"></span>
                <span><?php _e('1. 📦 Registrar Entrega / Anticipo', 'aura-suite'); ?></span>
            </button>
            <button type="button" class="aura-modal-step aura-petty-tab" data-tab="settlement" id="aura-petty-tab-settlement" style="cursor:pointer;flex:1;justify-content:center;">
                <span class="dashicons dashicons-clipboard" style="margin-right:4px;"></span>
                <span><?php _e('2. 📋 Rendir Fondos y Gastos', 'aura-suite'); ?></span>
                <span id="aura-petty-settlement-id-badge" class="aura-pill aura-pill--info aura-hidden" style="margin-left:6px;font-size:10px;"></span>
            </button>
        </div>

            <!-- Panel: Registrar Entrega -->
            <div class="aura-petty-panel is-active" id="aura-petty-panel-delivery">
                <div class="aura-petty-panel-info">
                    <span class="dashicons dashicons-info"></span>
                    <?php _e('Registra el fondo que entregas a un responsable. Luego podrás cargar este registro para rendir los gastos con sus comprobantes.', 'aura-suite'); ?>
                </div>

                <form id="aura-petty-delivery-form" class="aura-petty-delivery-form" enctype="multipart/form-data">
                    <div class="aura-petty-sections">
                        <!-- Bloque 1: Cuentas -->
                        <div class="aura-petty-card-block">
                            <h4 class="aura-petty-block-title">
                                <span class="dashicons dashicons-building"></span>
                                <?php _e('1. Cuentas y Destino del Fondo', 'aura-suite'); ?>
                            </h4>
                            <div class="aura-field-group cols-2">
                                <div class="aura-form-field">
                                    <label for="aura-petty-account">
                                        <strong><?php _e('Caja Chica del Área / Custodio (¿Dónde entra el dinero?) *', 'aura-suite'); ?></strong>
                                    </label>
                                    <select id="aura-petty-account" class="aura-input" required>
                                        <option value=""><?php _e('Selecciona la Caja Chica...', 'aura-suite'); ?></option>
                                    </select>
                                    <small class="description" style="display:block;margin-top:4px;color:#64748b;font-size:11.5px;">
                                        <?php _e('Cuenta de efectivo/caja chica asignada al área o persona responsable.', 'aura-suite'); ?>
                                    </small>
                                </div>
                                <div class="aura-form-field">
                                    <label for="aura-petty-origin-account">
                                        <strong><?php _e('Cuenta de Banco de Finanzas (¿De dónde sale el dinero?)', 'aura-suite'); ?></strong>
                                    </label>
                                    <select id="aura-petty-origin-account" class="aura-input">
                                        <option value=""><?php _e('Sin débito bancario (Efectivo manual ya disponible)', 'aura-suite'); ?></option>
                                    </select>
                                    <small class="description" style="display:block;margin-top:4px;color:#64748b;font-size:11.5px;">
                                        <?php _e('Cuenta bancaria institucional desde donde Finanzas transfiere o retira el dinero.', 'aura-suite'); ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Bloque 2: Responsable y Monto -->
                        <div class="aura-petty-card-block">
                            <h4 class="aura-petty-block-title">
                                <span class="dashicons dashicons-businessperson"></span>
                                <?php _e('2. Responsable, Importe y Plazo', 'aura-suite'); ?>
                            </h4>
                            <div class="aura-field-group cols-2">
                                <div class="aura-form-field aura-form-field--span2">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;gap:6px;flex-wrap:wrap;">
                                        <label for="aura-petty-responsible-name" style="margin-bottom:0;font-weight:600;">
                                            <strong><?php _e('Responsable / Beneficiario *', 'aura-suite'); ?></strong>
                                        </label>
                                        <button type="button" class="btn btn-secondary btn-lift aura-btn-open-tp-explorer" 
                                                data-target-input="#aura-petty-responsible-name" 
                                                data-target-hidden="#aura-petty-responsible" 
                                                data-target-avatar="#aura-petty-responsible-preview-avatar"
                                                data-target-text="#aura-petty-responsible-preview-text"
                                                data-target-badge="#aura-petty-responsible-preview-badge"
                                                data-target-preview="#aura-petty-responsible-preview"
                                                data-format="prefixed"
                                                style="font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:3px;height:24px;line-height:22px;padding:0 7px;" 
                                                title="<?php esc_attr_e('Abrir catálogo y explorador clasificado por rol contable', 'aura-suite'); ?>">
                                            <span class="dashicons dashicons-groups" style="font-size:13px;width:13px;height:13px;line-height:13px;"></span> <?php _e('Buscar Tercero', 'aura-suite'); ?>
                                        </button>
                                    </div>
                                    <div class="aura-counterparty-autocomplete-wrap" style="position:relative;">
                                        <input type="text" id="aura-petty-responsible-name" class="aura-input" autocomplete="off" placeholder="<?php esc_attr_e('Escribe o busca responsable...', 'aura-suite'); ?>" required>
                                        <input type="hidden" id="aura-petty-responsible" name="responsible_user_id" value="">
                                        <span class="dashicons dashicons-businessman" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;"></span>
                                    </div>
                                    <div id="aura-petty-responsible-preview" class="aura-entity-preview-card" style="display:none;margin-top:6px;align-items:center;gap:8px;border-radius:8px;padding:6px 10px;">
                                        <div id="aura-petty-responsible-preview-avatar" class="avatar-box" style="width:28px;height:28px;border-radius:6px;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;"></div>
                                        <div class="entity-info" style="flex:1;min-width:0;line-height:1.2;">
                                            <span id="aura-petty-responsible-preview-text" class="entity-name" style="font-weight:600;font-size:12px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                                            <span id="aura-petty-responsible-preview-badge" class="aura-pill aura-pill--muted entity-badge" style="font-size:10px;padding:1px 6px;"></span>
                                        </div>
                                        <button type="button" class="entity-clear-btn aura-preview-clear-btn" id="aura-petty-responsible-preview-clear" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:14px;padding:2px 4px;" title="<?php esc_attr_e('Quitar selección', 'aura-suite'); ?>">✕</button>
                                    </div>
                                </div>

                                <div class="aura-form-field aura-form-field--span2">
                                    <label style="margin-bottom:6px;font-weight:600;display:block;">
                                        <strong><?php _e('Naturaleza / Tipo de Fondo *', 'aura-suite'); ?></strong>
                                    </label>
                                    <div class="aura-petty-type-selector" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:10px;">
                                        <label class="aura-petty-type-card is-selected" data-type="purchase_errand" style="border:2px solid var(--aura-primary, #2563eb);background:var(--aura-primary-subtle, rgba(37,99,235,0.08));border-radius:10px;padding:10px 12px;cursor:pointer;display:flex;align-items:flex-start;gap:10px;transition:all 0.2s;">
                                            <input type="radio" name="aura_petty_type" value="purchase_errand" checked style="margin-top:2px;">
                                            <div style="line-height:1.3;">
                                                <div style="font-weight:700;font-size:13px;color:var(--aura-primary, #1e3a8a);display:flex;align-items:center;gap:4px;">
                                                    🛒 <?php _e('Compra puntual / Diligencia', 'aura-suite'); ?>
                                                </div>
                                                <div style="font-size:11.5px;color:var(--aura-text-muted, #475569);margin-top:3px;">
                                                    <?php _e('Dinero para compra rápida o trámite específico. Requiere facturas y reintegro en pocos días.', 'aura-suite'); ?>
                                                </div>
                                            </div>
                                        </label>
                                        <label class="aura-petty-type-card" data-type="program_budget" style="border:2px solid var(--aura-border, #cbd5e1);background:var(--aura-card-bg, #ffffff);border-radius:10px;padding:10px 12px;cursor:pointer;display:flex;align-items:flex-start;gap:10px;transition:all 0.2s;">
                                            <input type="radio" name="aura_petty_type" value="program_budget" style="margin-top:2px;">
                                            <div style="line-height:1.3;">
                                                <div style="font-weight:700;font-size:13px;color:var(--aura-text-primary, #0f172a);display:flex;align-items:center;gap:4px;">
                                                    📦 <?php _e('Presupuesto de Programa / Fondo Fijo', 'aura-suite'); ?>
                                                </div>
                                                <div style="font-size:11.5px;color:var(--aura-text-muted, #475569);margin-top:3px;">
                                                    <?php _e('Traspaso o entrega de presupuesto a otra caja/usuario para programas de 1 a 6 meses o más. No vence a corto plazo.', 'aura-suite'); ?>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div class="aura-form-field">
                                    <label for="aura-petty-delivered"><strong><?php _e('Monto a Entregar *', 'aura-suite'); ?></strong></label>
                                    <div class="aura-input-with-icon">
                                        <span class="aura-input-icon">$</span>
                                        <input type="number" id="aura-petty-delivered" class="aura-input aura-input--has-icon" min="0.01" step="0.01" placeholder="0.00" required>
                                    </div>
                                </div>

                                <div class="aura-form-field">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;flex-wrap:wrap;gap:4px;">
                                        <label for="aura-petty-due-date" id="aura-petty-due-date-label" style="margin-bottom:0;"><strong><?php _e('Fecha límite rendición', 'aura-suite'); ?></strong></label>
                                        <div class="aura-petty-shortcuts" id="aura-petty-shortcuts-container">
                                            <button type="button" class="aura-petty-shortcut-btn" data-days="3">+3d</button>
                                            <button type="button" class="aura-petty-shortcut-btn" data-days="5">+5d</button>
                                            <button type="button" class="aura-petty-shortcut-btn" data-days="7">+7d</button>
                                            <button type="button" class="aura-petty-shortcut-btn" data-days="15">+15d</button>
                                        </div>
                                    </div>
                                    <input type="date" id="aura-petty-due-date" class="aura-input">
                                    <small id="aura-petty-due-date-help" class="description" style="display:block;margin-top:4px;color:#64748b;font-size:11.5px;">
                                        <?php _e('Plazo límite para compra y devolución.', 'aura-suite'); ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Bloque 3: Propósito y Soporte -->
                        <div class="aura-petty-card-block">
                            <h4 class="aura-petty-block-title">
                                <span class="dashicons dashicons-media-document"></span>
                                <?php _e('3. Propósito del Fondo y Comprobante de Entrega', 'aura-suite'); ?>
                            </h4>
                            <div class="aura-field-group cols-2">
                                <div class="aura-form-field aura-form-field--span2">
                                    <label for="aura-petty-notes">
                                        <strong><?php _e('Notas / Propósito del Fondo *', 'aura-suite'); ?></strong>
                                        <small class="description" style="margin-left:4px;color:#64748b;font-weight:normal;"><?php _e('(Describa el objetivo o diligencia del fondo entregado)', 'aura-suite'); ?></small>
                                    </label>
                                    <textarea id="aura-petty-notes" class="aura-input" rows="2" placeholder="<?php esc_attr_e('Ej: Gastos operativos de programa, viáticos de transporte, suministros de emergencia...', 'aura-suite'); ?>" required></textarea>
                                </div>

                                <div class="aura-form-field aura-form-field--span2" style="display:none;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;flex-wrap:wrap;gap:8px;">
                                        <label for="aura-petty-delivery-receipt" style="margin-bottom:0;">
                                            <strong><?php _e('Comprobante de Egreso / Transferencia Inicial', 'aura-suite'); ?></strong>
                                            <small class="description"><?php _e('(Opcional pero recomendado)', 'aura-suite'); ?></small>
                                        </label>
                                        <div class="aura-petty-receipt-type-toggle" style="display:inline-flex;gap:4px;background:#f1f5f9;padding:2px;border-radius:6px;">
                                            <button type="button" class="button button-small aura-petty-receipt-mode-btn is-active" data-mode="file" style="border:none;background:#ffffff;box-shadow:0 1px 2px rgba(0,0,0,0.06);font-size:11px;padding:1px 8px;border-radius:4px;font-weight:600;">
                                                <span class="dashicons dashicons-upload" style="font-size:12px;width:12px;height:12px;line-height:12px;"></span> <?php _e('Subir Archivo', 'aura-suite'); ?>
                                            </button>
                                            <button type="button" class="button button-small aura-petty-receipt-mode-btn" data-mode="url" style="border:none;background:transparent;box-shadow:none;color:#64748b;font-size:11px;padding:1px 8px;border-radius:4px;font-weight:600;">
                                                <span class="dashicons dashicons-cloud-saved" style="font-size:12px;width:12px;height:12px;line-height:12px;"></span> <?php _e('Google Drive / Enlace', 'aura-suite'); ?>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Modo 1: Archivo local / foto -->
                                    <div id="aura-petty-delivery-receipt-file-wrap" class="aura-petty-file-select-box">
                                        <input type="file" id="aura-petty-delivery-receipt" name="delivery_receipt_file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="aura-input" style="padding:6px;">
                                        <?php if ($petty_drive_ready): ?>
                                            <small class="aura-petty-storage-hint is-drive" style="display:flex;align-items:center;gap:4px;margin-top:4px;color:#0284c7;font-size:11.5px;">
                                                <span class="dashicons dashicons-cloud-saved"></span> <?php _e('Sincronización en la nube: Google Drive Activo', 'aura-suite'); ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="aura-petty-storage-hint is-local" style="display:flex;align-items:center;gap:4px;margin-top:4px;color:#64748b;font-size:11.5px;">
                                                <span class="dashicons dashicons-portfolio"></span> <?php _e('Almacenamiento seguro en servidor local', 'aura-suite'); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Modo 2: Enlace directo Google Drive / URL -->
                                    <div id="aura-petty-delivery-receipt-url-wrap" style="display:none;">
                                        <div class="aura-input-group">
                                            <span class="aura-input-group-text" style="color:#0284c7;background:#eff6ff;border-color:#bfdbfe;">
                                                <span class="dashicons dashicons-admin-links"></span>
                                            </span>
                                            <input type="url" id="aura-petty-delivery-receipt-url" name="delivery_receipt_url" class="aura-input" placeholder="https://drive.google.com/file/d/... o enlace bancario">
                                        </div>
                                        <small class="description" style="display:block;margin-top:4px;color:#64748b;font-size:11.5px;">
                                            <?php _e('Pega aquí el enlace de Google Drive o comprobante digital para vincularlo directamente sin mezclarlo en las notas.', 'aura-suite'); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="aura-petty-actions">
                        <button type="submit" class="btn btn-primary btn-shimmer btn-lift" id="aura-petty-create-btn">
                            <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-right:2px;"></span>
                            <?php _e('Registrar Entrega de Fondo', 'aura-suite'); ?>
                        </button>
                        <button type="button" class="btn btn-secondary btn-lift" id="aura-petty-reset-btn"><?php _e('Limpiar', 'aura-suite'); ?></button>
                    </div>
                </form>
            </div>

            <!-- Panel: Rendir Fondos -->
            <div class="aura-petty-panel" id="aura-petty-panel-settlement">
                <div class="aura-petty-panel-info is-warning" id="aura-petty-settle-info">
                    <span class="dashicons dashicons-warning"></span>
                    <?php _e('Selecciona una entrega de la tabla (ícono ✏️) para cargar los datos y rendir los gastos aquí.', 'aura-suite'); ?>
                </div>

                <!-- Resumen de la entrega cargada -->
                <div id="aura-petty-loaded-summary" class="aura-petty-loaded-summary aura-hidden">
                    <div class="aura-petty-summary-item">
                        <span><?php _e('Responsable', 'aura-suite'); ?></span>
                        <strong id="aura-petty-sum-responsible">—</strong>
                    </div>
                    <div class="aura-petty-summary-item">
                        <span><?php _e('Cuenta de Caja', 'aura-suite'); ?></span>
                        <strong id="aura-petty-sum-account">—</strong>
                    </div>
                    <div class="aura-petty-summary-item">
                        <span><?php _e('Fondo Entregado', 'aura-suite'); ?></span>
                        <strong id="aura-petty-sum-delivered" class="is-amount">—</strong>
                    </div>
                    <div class="aura-petty-summary-item">
                        <span><?php _e('Fecha Límite', 'aura-suite'); ?></span>
                        <strong id="aura-petty-sum-due">—</strong>
                    </div>
                </div>

                <!-- PANEL KPI DE ARQUEO FINANCIERO EN TIEMPO REAL -->
                <div class="aura-petty-kpi-grid" id="aura-petty-kpi-grid">
                    <!-- Tarjeta 1: Fondo Asignado -->
                    <div class="aura-petty-kpi-card is-delivered">
                        <div class="aura-petty-kpi-header">
                            <span class="aura-petty-kpi-icon dashicons dashicons-money-alt"></span>
                            <span class="aura-petty-kpi-label"><?php _e('Fondo Entregado', 'aura-suite'); ?></span>
                        </div>
                        <div class="aura-petty-kpi-val" id="aura-petty-kpi-delivered">$ 0.00</div>
                        <div class="aura-petty-kpi-sub"><?php _e('Capital base asignado', 'aura-suite'); ?></div>
                    </div>

                    <!-- Tarjeta 2: Total Gastado / Comprobado -->
                    <div class="aura-petty-kpi-card is-spent">
                        <div class="aura-petty-kpi-header">
                            <span class="aura-petty-kpi-icon dashicons dashicons-media-spreadsheet"></span>
                            <span class="aura-petty-kpi-label"><?php _e('Total Gastado', 'aura-suite'); ?></span>
                        </div>
                        <div class="aura-petty-kpi-val" id="aura-petty-kpi-spent">$ 0.00</div>
                        <div class="aura-petty-progress">
                            <div id="aura-petty-progress-bar" class="aura-petty-progress-fill" style="width: 0%;"></div>
                        </div>
                        <div class="aura-petty-kpi-sub">
                            <span id="aura-petty-kpi-receipt-count">0 comprobantes</span>
                            (<span id="aura-petty-kpi-percent">0%</span> <?php _e('consumido', 'aura-suite'); ?>)
                        </div>
                    </div>

                    <!-- Tarjeta 3: Arqueo / Balance Final (Devuelto o Excedente) -->
                    <div class="aura-petty-kpi-card is-balance is-return" id="aura-petty-kpi-card-balance">
                        <div class="aura-petty-kpi-header">
                            <span class="aura-petty-kpi-icon dashicons dashicons-shield-alt" id="aura-petty-kpi-balance-icon"></span>
                            <span class="aura-petty-kpi-label" id="aura-petty-kpi-balance-title"><?php _e('Efectivo por Devolver', 'aura-suite'); ?></span>
                            <span id="aura-petty-kpi-badge" class="aura-petty-kpi-badge"><?php _e('Reintegro', 'aura-suite'); ?></span>
                        </div>
                        <div class="aura-petty-kpi-val" id="aura-petty-kpi-balance-val">$ 0.00</div>
                        <div class="aura-petty-kpi-sub" id="aura-petty-kpi-balance-desc">
                            <?php _e('Efectivo que el responsable debe reintegrar al fondo.', 'aura-suite'); ?>
                        </div>
                    </div>
                </div>

                <form id="aura-petty-cash-form" class="aura-petty-cash-form">
                    <!-- Inputs técnicos ocultos para cálculo y envío -->
                    <input type="hidden" id="aura-petty-spent" value="0">
                    <input type="hidden" id="aura-petty-returned" value="0">
                    <div id="aura-petty-excedent-msg" style="display:none;"></div>

                    <!-- Tabla de Gastos / Comprobantes -->
                    <div class="aura-petty-expenses-section">
                        <div class="aura-petty-expenses-head">
                            <div>
                                <h4 style="margin:0;font-size:14px;font-weight:700;color:var(--aura-text-main,#1e293b);">
                                    <span class="dashicons dashicons-list-view" style="color:var(--aura-primary,#0284c7);vertical-align:middle;margin-right:4px;"></span>
                                    <?php _e('Detalle de Gastos y Comprobantes', 'aura-suite'); ?>
                                </h4>
                                <p class="description" style="margin:2px 0 0;font-size:12px;">
                                    <?php _e('Registra cada gasto individual con su respectiva categoría contable y concepto.', 'aura-suite'); ?>
                                </p>
                            </div>
                            <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="aura-petty-add-expense-btn" style="display:inline-flex;align-items:center;gap:4px;">
                                <span class="dashicons dashicons-plus-alt2" style="font-size:15px;width:15px;height:15px;line-height:15px;"></span>
                                <?php _e('Añadir Gasto', 'aura-suite'); ?>
                            </button>
                        </div>

                        <div class="aura-dt-wrapper aura-petty-table-container">
                            <table class="aura-petty-expenses-table" id="aura-petty-expenses-table">
                                <thead>
                                    <tr>
                                        <th style="width:140px;"><?php _e('Monto ($) *', 'aura-suite'); ?></th>
                                        <th style="width:200px;"><?php _e('Categoría Contable *', 'aura-suite'); ?></th>
                                        <th><?php _e('Concepto / Justificación del Gasto *', 'aura-suite'); ?></th>
                                        <th style="width:45px;text-align:center;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Filas dinámicas generadas por JS -->
                                </tbody>
                            </table>
                        </div>

                        <template id="aura-petty-expense-row-template">
                            <tr class="aura-petty-expense-row">
                                <td style="width:140px;">
                                    <div class="aura-input-with-icon">
                                        <span class="aura-input-icon">$</span>
                                        <input type="number" class="aura-petty-expense-amount aura-input-amount" min="0.01" step="0.01" value="" placeholder="0.00" required>
                                    </div>
                                </td>
                                <td style="width:200px;">
                                    <select class="aura-petty-expense-category aura-input" required>
                                        <option value=""><?php _e('Seleccionar categoría...', 'aura-suite'); ?></option>
                                        <?php
                                        global $wpdb;
                                        $cats = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}aura_finance_categories WHERE is_active = 1 ORDER BY name ASC");
                                        foreach ($cats as $cat) {
                                            echo '<option value="' . esc_attr($cat->id) . '">' . esc_html($cat->name) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="aura-petty-expense-concept aura-input" placeholder="<?php esc_attr_e('Ej: Compra de papelería, taxi a notaría, suministros...', 'aura-suite'); ?>" required>
                                </td>
                                <td style="width:45px;text-align:center;">
                                    <button type="button" class="btn btn-danger btn-sm aura-petty-expense-remove" title="<?php esc_attr_e('Eliminar este gasto', 'aura-suite'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </div>

                    <!-- AVISO: COMPROBANTES SE GESTIONAN EN TRANSACCIONES -->
                    <div class="aura-callout aura-callout--info" style="margin-top:16px;padding:12px 16px;border-radius:8px;background:#f0f9ff;border:1px solid #bae6fd;display:flex;align-items:center;gap:12px;">
                        <span class="dashicons dashicons-info" style="color:#0284c7;font-size:22px;width:22px;height:22px;line-height:22px;flex-shrink:0;"></span>
                        <div style="font-size:12px;color:#0369a1;line-height:1.45;">
                            <strong><?php _e('Comprobantes y Soportes:', 'aura-suite'); ?></strong>
                            <?php _e('No es necesario adjuntar comprobantes o facturas en esta pantalla; estos se anexan y concilian directamente en el módulo de Transacciones.', 'aura-suite'); ?>
                        </div>
                    </div>

                    <!-- Referencias adicionales / Observaciones -->
                    <div class="aura-petty-settle-notes-wrap" style="margin-top:14px;">
                        <label for="aura-petty-settle-notes"><strong><?php _e('Observaciones Generales de la Rendición', 'aura-suite'); ?></strong> <small class="description"><?php _e('(opcional)', 'aura-suite'); ?></small></label>
                        <textarea id="aura-petty-settle-notes" class="aura-input" rows="2" placeholder="<?php esc_attr_e('Detalles adicionales, conceptos o aclaraciones de la rendición...', 'aura-suite'); ?>"></textarea>
                        <input type="hidden" id="aura-petty-evidence" value="">
                    </div>

                    <!-- ZONA DE COMPROBANTES DIGITALES OCULTA (Mantenida en DOM para compatibilidad técnica de scripts) -->
                    <div class="aura-petty-cloud-section" style="display:none !important;">
                        <div class="aura-petty-cloud-header">
                            <div>
                                <h4 style="margin:0;font-size:13px;font-weight:700;color:var(--aura-text-main,#1e293b);">
                                    <span class="dashicons dashicons-paperclip" style="vertical-align:middle;margin-right:4px;"></span>
                                    <?php _e('Comprobantes y Soportes Digitales', 'aura-suite'); ?>
                                </h4>
                            </div>
                        </div>
                        <div class="aura-petty-dropzone" id="aura-petty-dropzone">
                            <input type="file" id="aura-petty-evidence-files" multiple accept=".jpg,.jpeg,.png,.pdf,.webp" style="display:none;">
                            <div class="aura-petty-dropzone-content">
                                <span class="dashicons dashicons-cloud-upload aura-petty-dropzone-icon"></span>
                            </div>
                        </div>
                        <div id="aura-petty-files-preview-list" class="aura-petty-files-list"></div>
                    </div>

                    <div class="aura-petty-actions">
                        <button type="submit" class="btn btn-primary btn-shimmer btn-lift" id="aura-petty-submit-btn" disabled>
                            <span class="dashicons dashicons-saved" style="vertical-align:middle;margin-right:2px;"></span>
                            <?php _e('Enviar Rendición para Aprobación', 'aura-suite'); ?>
                        </button>
                        <button type="button" class="btn btn-secondary btn-lift" id="aura-petty-clear-settle-btn"><?php _e('Cancelar', 'aura-suite'); ?></button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<!-- MODAL DE VISUALIZACIÓN DE EVIDENCIAS Y RECIBOS -->
<div id="aura-petty-evidence-modal" class="aura-finance-modal aura-petty-evidence-modal" style="display:none;" aria-hidden="true">
    <div class="aura-finance-modal__backdrop" id="aura-petty-evidence-modal-backdrop" data-modal-close="aura-petty-evidence-modal"></div>
    <div class="aura-finance-modal__dialog aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-petty-evidence-modal-title">
        <div class="aura-finance-modal__head">
            <div>
                <h2 id="aura-petty-evidence-modal-title" style="display:flex;align-items:center;gap:8px;">
                    <span class="dashicons dashicons-media-document" style="color:var(--aura-primary,#0284c7);"></span>
                    <?php _e('Comprobantes de Rendición', 'aura-suite'); ?>
                </h2>
                <p class="description"><?php _e('Facturas, recibos y soportes cargados para esta liquidación de fondos.', 'aura-suite'); ?></p>
            </div>
            <button type="button" class="aura-modal-close" id="aura-petty-evidence-modal-close" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
        </div>
        <div id="aura-petty-evidence-content" class="aura-finance-modal__body aura-petty-evidence-modal__content" style="padding-top:6px;">
            <!-- Inyectado dinámicamente vía JS -->
        </div>
    </div>
</div>
    <div id="aura-finance-exchange-modal" class="aura-modal-overlay" aria-hidden="true">
    
        <div class="aura-modal-content aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-exchange-modal-title">
            <div class="aura-modal-header">
                <div>
                    <h2 id="aura-exchange-modal-title" class="aura-title-with-help"><?php _e('Cambio de Divisa (Compra y Venta)', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Transfiere fondos entre cuentas de diferente moneda (Local y USD) aplicando la tasa de cambio correspondiente.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h2>
                    <p><?php _e('Convierte fondos entre tu moneda local y caja/banco en USD de forma segura.', 'aura-suite'); ?></p>
                </div>
                <button type="button" class="aura-modal-close" data-modal-close="aura-finance-exchange-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>

            <form id="aura-exchange-form" class="aura-modal-body">
                <div class="aura-field-group cols-2">
                    <!-- Selector de Divisa Extranjera a Operar -->
                    <div class="aura-field aura-field--span2 aura-exchange-currency-box" style="margin-bottom: 6px;">
                        <label for="aura-exchange-currency-select" class="aura-label" style="font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--aura-text-muted, #64748b);">
                            <?php _e('Divisa Extranjera a Operar', 'aura-suite'); ?>
                        </label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <select id="aura-exchange-currency-select" class="aura-input" style="max-width: 280px; font-weight: 600;">
                                <option value="USD" selected><?php _e('USD - Dólar Estadounidense ($)', 'aura-suite'); ?></option>
                                <option value="EUR"><?php _e('EUR - Euro (€)', 'aura-suite'); ?></option>
                                <option value="CAD"><?php _e('CAD - Dólar Canadiense (C$)', 'aura-suite'); ?></option>
                            </select>
                            <small style="font-size: 11.5px; color: var(--aura-text-muted, #64748b);">
                                <?php _e('Selecciona la divisa extranjera para comprar o vender.', 'aura-suite'); ?>
                            </small>
                        </div>
                    </div>

                    <!-- Selector de Dirección estilo Radio Cards -->
                    <div class="aura-field aura-field--span2 aura-exchange-direction-box">
                        <label class="aura-label" style="margin-bottom: 8px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--aura-text-muted, #64748b);">
                            <?php _e('Dirección de la Operación', 'aura-suite'); ?>
                        </label>
                        <div class="aura-exchange-direction-cards">
                            <label class="aura-exchange-dir-card is-active">
                                <input type="radio" name="exchange_direction" value="local_to_usd" checked>
                                <div>
                                    <strong id="aura-exchange-dir-buy-title"><?php _e('Comprar Dólares', 'aura-suite'); ?></strong>
                                    <span id="aura-exchange-dir-buy-sub"><?php _e('Moneda Local ➔ Caja USD', 'aura-suite'); ?></span>
                                </div>
                            </label>
                            <label class="aura-exchange-dir-card">
                                <input type="radio" name="exchange_direction" value="usd_to_local">
                                <div>
                                    <strong id="aura-exchange-dir-sell-title"><?php _e('Vender Dólares', 'aura-suite'); ?></strong>
                                    <span id="aura-exchange-dir-sell-sub"><?php _e('Caja USD ➔ Moneda Local', 'aura-suite'); ?></span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Cuenta Origen -->
                    <div class="aura-field">
                        <label for="aura-exchange-source" class="aura-label">
                            <strong id="aura-exchange-source-label"><?php _e('Cuenta Origen (Moneda Local)', 'aura-suite'); ?></strong>
                            <span class="aura-required-star" style="color: #ef4444;">*</span>
                        </label>
                        <select id="aura-exchange-source" name="source_account_id" class="aura-input" required>
                            <option value=""><?php _e('Selecciona cuenta origen...', 'aura-suite'); ?></option>
                        </select>
                        <small id="aura-exchange-source-balance-hint" style="font-size: 11.5px; color: var(--aura-text-muted, #64748b); display: block; margin-top: 3px;"></small>
                    </div>

                    <!-- Cuenta Destino -->
                    <div class="aura-field">
                        <label for="aura-exchange-target" class="aura-label">
                            <strong id="aura-exchange-target-label"><?php _e('Cuenta Destino (USD)', 'aura-suite'); ?></strong>
                            <span class="aura-required-star" style="color: #ef4444;">*</span>
                        </label>
                        <select id="aura-exchange-target" name="target_account_id" class="aura-input" required>
                            <option value=""><?php _e('Selecciona cuenta destino...', 'aura-suite'); ?></option>
                        </select>
                        <small id="aura-exchange-target-balance-hint" style="font-size: 11.5px; color: var(--aura-text-muted, #64748b); display: block; margin-top: 3px;"></small>
                    </div>

                    <!-- Tasa de Cambio (Input Group) -->
                    <div class="aura-field aura-field--span2">
                        <label for="aura-exchange-rate" class="aura-label">
                            <strong id="aura-exchange-rate-label"><?php _e('Tasa de Cambio (1 USD = X Local)', 'aura-suite'); ?></strong>
                            <span class="aura-required-star" style="color: #ef4444;">*</span>
                            <span class="aura-help-tip" data-tooltip="<?php esc_attr_e('Indica cuántas unidades de moneda local equivalen a 1 unidad de la divisa extranjera.', 'aura-suite'); ?>" style="margin-left: 4px;">?</span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-addon">📈 Tasa</span>
                            <input type="number" id="aura-exchange-rate" class="aura-input" name="exchange_rate" min="0.0001" step="0.0001" required placeholder="18.00">
                        </div>
                    </div>

                    <!-- Monto en Divisa Extranjera (Input Group) -->
                    <div class="aura-field">
                        <label for="aura-exchange-amount-usd" class="aura-label">
                            <strong id="aura-exchange-amount-usd-label"><?php _e('Monto en Divisa Extranjera (USD)', 'aura-suite'); ?></strong>
                            <span class="aura-required-star" style="color: #ef4444;">*</span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-addon" id="aura-exchange-foreign-symbol" style="color: #10b981; font-weight: 700;">$</span>
                            <input type="number" id="aura-exchange-amount-usd" class="aura-input" name="amount_usd" min="0.01" step="0.01" required placeholder="200.00">
                            <span class="aura-input-group-addon" id="aura-exchange-foreign-code">USD</span>
                        </div>
                        <small style="font-size: 11px; color: var(--aura-text-muted, #94a3b8); margin-top: 3px; display: block;"><?php _e('Escribe aquí o en Moneda Local', 'aura-suite'); ?></small>
                    </div>

                    <!-- Monto en Moneda Local (Input Group) -->
                    <div class="aura-field">
                        <label for="aura-exchange-amount-local-input" class="aura-label">
                            <strong id="aura-exchange-amount-local-label"><?php _e('Monto en Moneda Local', 'aura-suite'); ?></strong>
                            <span class="aura-required-star" style="color: #ef4444;">*</span>
                        </label>
                        <div class="aura-input-group">
                            <span class="aura-input-group-addon" style="color: #3b82f6; font-weight: 700;">$</span>
                            <input type="number" id="aura-exchange-amount-local-input" class="aura-input" min="0.01" step="0.01" placeholder="3600.00">
                            <span class="aura-input-group-addon" id="aura-exchange-local-addon-curr">Local</span>
                        </div>
                        <small style="font-size: 11px; color: var(--aura-text-muted, #94a3b8); margin-top: 3px; display: block;"><?php _e('Calcula automáticamente el USD', 'aura-suite'); ?></small>
                    </div>

                    <!-- Tarjeta de Resumen Visual de Impacto de Saldos (prompt-maestro.md) -->
                    <div class="aura-field aura-field--span2 aura-exchange-impact-box">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--aura-text-heading, #0f172a);">
                                <?php _e('Resumen de Movimiento Contable', 'aura-suite'); ?>
                            </strong>
                            <span class="aura-badge aura-badge-blue" id="aura-exchange-summary-badge" style="font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight: 600;">
                                <?php _e('Reclasificación de Tesorería', 'aura-suite'); ?>
                            </span>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div class="aura-exchange-impact-subcard">
                                <div style="font-size: 11px; color: #ef4444; font-weight: 700; margin-bottom: 2px;">
                                    🔻 <?php _e('SE DEBITARÁ (SALIDA)', 'aura-suite'); ?>
                                </div>
                                <div id="aura-exchange-impact-source-text" style="font-size: 13px; color: var(--aura-text-heading, #0f172a); font-weight: 600;">
                                    —
                                </div>
                                <div id="aura-exchange-impact-source-sub" style="font-size: 11.5px; color: var(--aura-text-muted, #64748b); margin-top: 2px;">
                                    <?php _e('Selecciona cuenta origen', 'aura-suite'); ?>
                                </div>
                            </div>

                            <div class="aura-exchange-impact-subcard">
                                <div style="font-size: 11px; color: #10b981; font-weight: 700; margin-bottom: 2px;">
                                    🟢 <?php _e('SE ACREDITARÁ (ENTRADA)', 'aura-suite'); ?>
                                </div>
                                <div id="aura-exchange-impact-target-text" style="font-size: 13px; color: var(--aura-text-heading, #0f172a); font-weight: 600;">
                                    —
                                </div>
                                <div id="aura-exchange-impact-target-sub" style="font-size: 11.5px; color: var(--aura-text-muted, #64748b); margin-top: 2px;">
                                    <?php _e('Selecciona cuenta destino', 'aura-suite'); ?>
                                </div>
                            </div>
                        </div>

                        <div id="aura-exchange-balance-warning" style="display: none; margin-top: 10px; padding: 8px 12px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px; color: #dc2626; font-size: 12px; font-weight: 600;">
                            ⚠️ <?php _e('Saldo insuficiente en la cuenta de origen para cubrir esta operación.', 'aura-suite'); ?>
                        </div>
                    </div>

                    <!-- Notas / Referencia -->
                    <div class="aura-field aura-field--span2">
                        <label for="aura-exchange-notes" class="aura-label"><strong><?php _e('Notas, Justificación o Referencia', 'aura-suite'); ?></strong></label>
                        <textarea id="aura-exchange-notes" class="aura-input" name="notes" rows="2" placeholder="<?php esc_attr_e('Ej: Compra de 200 USD para caja chica en efectivo con fondos de Banco...', 'aura-suite'); ?>"></textarea>
                    </div>
                </div>

                <div class="aura-modal-footer aura-modal-footer-spaced aura-modal-footer-end">
                    <button type="button" class="btn btn-secondary btn-lift" data-modal-close="aura-finance-exchange-modal"><?php _e('Cancelar', 'aura-suite'); ?></button>
                    <button type="submit" class="btn btn-primary btn-shimmer btn-lift" id="aura-exchange-save-btn"><?php _e('Confirmar Cambio de Divisa', 'aura-suite'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detalle y Auditoría de Cambio de Divisa -->
    <div id="aura-finance-exchange-detail-modal" class="aura-modal-overlay" aria-hidden="true">
        <div class="aura-modal-content aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-exchange-detail-title" style="max-width: 650px;">
            <div class="aura-modal-header">
                <div>
                    <h2 id="aura-exchange-detail-title" class="aura-title-with-help">
                        <span class="dashicons dashicons-analytics" style="margin-right: 6px; color: #2563eb;"></span>
                        <?php _e('Auditoría de Cambio de Divisa', 'aura-suite'); ?>
                    </h2>
                    <p class="description" style="margin: 2px 0 0; font-size: 13px;">
                        <?php _e('Trazabilidad contable completa de la operación y movimientos asociados.', 'aura-suite'); ?>
                    </p>
                </div>
                <button type="button" class="aura-modal-close" data-modal-close="aura-finance-exchange-detail-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>
            <div class="aura-modal-body" style="padding-top: 16px;">
                <div id="aura-exchange-detail-content">
                    <div style="text-align: center; padding: 20px; color: var(--aura-text-muted, #888);">
                        <span class="dashicons dashicons-update aura-spin"></span> <?php _e('Cargando auditoría...', 'aura-suite'); ?>
                    </div>
                </div>
            </div>
            <div class="aura-modal-footer aura-modal-footer-end">
                <button type="button" class="btn btn-secondary btn-lift" data-modal-close="aura-finance-exchange-detail-modal"><?php _e('Cerrar', 'aura-suite'); ?></button>
            </div>
        </div>
    </div>

    <!-- Modal Editar Notas / Referencia de Cambio de Divisa -->
    <div id="aura-finance-exchange-edit-modal" class="aura-modal-overlay" aria-hidden="true">
        <div class="aura-modal-content aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-exchange-edit-title" style="max-width: 600px;">
            <div class="aura-modal-header">
                <div>
                    <h2 id="aura-exchange-edit-title" class="aura-title-with-help">
                        <span class="dashicons dashicons-edit" style="margin-right: 6px; color: #0284c7;"></span>
                        <?php _e('Editar Operación de Cambio de Divisa', 'aura-suite'); ?>
                    </h2>
                    <p class="description" style="margin: 2px 0 0; font-size: 13px;">
                        <?php _e('Modifica cuentas, tasa, montos o justificación. El sistema rebalanceará los saldos contables automáticamente.', 'aura-suite'); ?>
                    </p>
                </div>
                <button type="button" class="aura-modal-close" data-modal-close="aura-finance-exchange-edit-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>
            <form id="aura-exchange-edit-form" class="aura-modal-body" style="padding-top: 16px;">
                <input type="hidden" id="aura-exchange-edit-id" name="id" value="0">
                <input type="hidden" id="aura-exchange-edit-direction" name="direction" value="usd_to_local">

                <div class="aura-exchange-recalc-box" style="margin-bottom: 14px; padding: 10px 14px; border-radius: 8px; font-size: 12.5px; line-height: 1.5; background: rgba(2, 132, 199, 0.08); border: 1px solid rgba(2, 132, 199, 0.25); color: var(--aura-text-main, #334155);">
                    <span class="dashicons dashicons-info" style="font-size: 16px; margin-right: 4px; vertical-align: text-bottom; color: #0284c7;"></span>
                    <span id="aura-exchange-edit-direction-badge" style="font-weight: 600;"></span> — <?php _e('Cualquier cambio en montos o cuentas anulará el impacto anterior y aplicará los nuevos débitos/créditos de forma atómica.', 'aura-suite'); ?>
                </div>

                <div class="aura-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="aura-field">
                        <label for="aura-exchange-edit-source" class="aura-label"><strong><?php _e('Cuenta Origen (Salida)', 'aura-suite'); ?></strong></label>
                        <select id="aura-exchange-edit-source" name="source_account_id" class="aura-input aura-select" required></select>
                    </div>
                    <div class="aura-field">
                        <label for="aura-exchange-edit-target" class="aura-label"><strong><?php _e('Cuenta Destino (Entrada)', 'aura-suite'); ?></strong></label>
                        <select id="aura-exchange-edit-target" name="target_account_id" class="aura-input aura-select" required></select>
                    </div>
                </div>

                <div class="aura-form-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="aura-field">
                        <label for="aura-exchange-edit-rate" class="aura-label"><strong><?php _e('Tasa de Cambio', 'aura-suite'); ?></strong></label>
                        <input type="number" id="aura-exchange-edit-rate" name="exchange_rate" class="aura-input" step="0.0001" min="0.0001" required>
                    </div>
                    <div class="aura-field">
                        <label for="aura-exchange-edit-amount-usd" class="aura-label"><strong id="aura-exchange-edit-amount-foreign-label"><?php _e('Monto Divisa Extranjera', 'aura-suite'); ?></strong></label>
                        <input type="number" id="aura-exchange-edit-amount-usd" name="amount_usd" class="aura-input" step="0.01" min="0.01" required>
                    </div>
                    <div class="aura-field">
                        <label for="aura-exchange-edit-amount-local" class="aura-label"><strong><?php _e('Monto Local', 'aura-suite'); ?></strong></label>
                        <input type="number" id="aura-exchange-edit-amount-local" name="amount_local" class="aura-input" step="0.01" min="0.01" readonly style="background: rgba(0,0,0,0.03); font-weight: 600;">
                    </div>
                </div>

                <div class="aura-field" style="margin-bottom: 12px;">
                    <label for="aura-exchange-edit-notes" class="aura-label"><strong><?php _e('Notas, Justificación o Referencia', 'aura-suite'); ?></strong></label>
                    <textarea id="aura-exchange-edit-notes" class="aura-input" name="notes" rows="3" required placeholder="<?php esc_attr_e('Indica el motivo o referencia de la operación...', 'aura-suite'); ?>"></textarea>
                </div>

                <div class="aura-modal-footer aura-modal-footer-spaced aura-modal-footer-end" style="margin-top: 16px; padding: 0;">
                    <button type="button" class="btn btn-secondary btn-lift" data-modal-close="aura-finance-exchange-edit-modal"><?php _e('Cancelar', 'aura-suite'); ?></button>
                    <button type="submit" class="btn btn-primary btn-shimmer btn-lift" id="aura-exchange-edit-save-btn"><?php _e('Guardar y Rebalancear', 'aura-suite'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Anular / Revertir Operación de Cambio de Divisa -->
    <div id="aura-finance-exchange-revert-modal" class="aura-modal-overlay" aria-hidden="true">
        <div class="aura-modal-content aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-exchange-revert-title" style="max-width: 520px;">
            <div class="aura-modal-header">
                <div>
                    <h2 id="aura-exchange-revert-title" class="aura-title-with-help" style="color: #dc2626;">
                        <span class="dashicons dashicons-undo" style="margin-right: 6px;"></span>
                        <?php _e('Anular y Revertir Operación', 'aura-suite'); ?>
                    </h2>
                    <p class="description" style="margin: 2px 0 0; font-size: 13px;">
                        <?php _e('Se restituirá el saldo debitado a la cuenta origen y se descontará el dinero de la cuenta destino.', 'aura-suite'); ?>
                    </p>
                </div>
                <button type="button" class="aura-modal-close" data-modal-close="aura-finance-exchange-revert-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>
            <form id="aura-exchange-revert-form" class="aura-modal-body" style="padding-top: 16px;">
                <input type="hidden" id="aura-exchange-revert-id" name="id" value="0">
                <div id="aura-exchange-revert-summary" style="margin-bottom: 14px; padding: 10px 14px; border-radius: 8px; font-size: 12.5px; line-height: 1.5; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); color: var(--aura-text-main, #334155);"></div>
                <div class="aura-field">
                    <label for="aura-exchange-revert-reason" class="aura-label"><strong style="color: #dc2626;"><?php _e('Motivo de la Anulación / Reversión *', 'aura-suite'); ?></strong></label>
                    <textarea id="aura-exchange-revert-reason" class="aura-input" name="revert_reason" rows="3" required placeholder="<?php esc_attr_e('Ej: Operación duplicada por error, tasa errónea, fondos devueltos...', 'aura-suite'); ?>"></textarea>
                </div>
                <div class="aura-modal-footer aura-modal-footer-spaced aura-modal-footer-end" style="margin-top: 16px; padding: 0;">
                    <button type="button" class="btn btn-secondary btn-lift" data-modal-close="aura-finance-exchange-revert-modal"><?php _e('Cancelar', 'aura-suite'); ?></button>
                    <button type="submit" class="btn btn-danger btn-shimmer btn-lift" id="aura-exchange-revert-save-btn"><?php _e('Confirmar Reversión', 'aura-suite'); ?></button>
                </div>
            </form>
        </div>
    </div>
    <!-- Modal Cuenta -->
    <div id="aura-finance-account-modal" class="aura-modal-overlay" aria-hidden="true">
        <div class="aura-modal-content aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-account-form-title">
            <div class="aura-modal-header">
                <div>
                    <h2 id="aura-account-form-title" class="aura-title-with-help"><?php _e('Nueva Cuenta', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Completa tipo, moneda y saldos base. El saldo actual debe reflejar el valor operativo real.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h2>
                    <p><?php _e('Crea o edita cuentas financieras con datos operativos y saldo base.', 'aura-suite'); ?></p>
                </div>
                <button type="button" class="aura-modal-close" data-modal-close="aura-finance-account-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>
            <form id="aura-account-form" class="aura-modal-body">
                <input type="hidden" name="id" id="aura-account-id" value="0">
                <div class="aura-field-group cols-2">
                    <div class="aura-field">
                        <label for="aura-account-name" class="aura-label"><strong><?php _e('Nombre', 'aura-suite'); ?></strong></label>
                        <input type="text" class="aura-input" name="name" id="aura-account-name" required>
                    </div>
                    <div class="aura-field">
                        <label for="aura-account-type" class="aura-label"><strong><?php _e('Tipo de cuenta', 'aura-suite'); ?></strong></label>
                        <select name="account_type" id="aura-account-type" class="aura-input">
                            <option value="bank_account"><?php _e('Cuenta Bancaria', 'aura-suite'); ?></option>
                            <option value="petty_cash"><?php _e('Caja Chica', 'aura-suite'); ?></option>
                            <option value="contributions_fund"><?php _e('Aportes', 'aura-suite'); ?></option>
                            <option value="usd_cash"><?php _e('Caja USD ($)', 'aura-suite'); ?></option>
                            <option value="eur_cash"><?php _e('Caja EUR (€)', 'aura-suite'); ?></option>
                            <option value="cad_cash"><?php _e('Caja CAD (C$)', 'aura-suite'); ?></option>
                            <option value="foreign_cash"><?php _e('Caja Divisa Extranjera', 'aura-suite'); ?></option>
                            <option value="custom"><?php _e('Otro', 'aura-suite'); ?></option>
                        </select>
                    </div>
                    <div class="aura-field">
                        <label for="aura-account-currency" class="aura-label"><strong><?php _e('Moneda', 'aura-suite'); ?></strong></label>
                        <input type="text" class="aura-input" name="currency" id="aura-account-currency" required value="COP">
                    </div>
                    <div class="aura-field">
                        <label for="aura-account-institution" class="aura-label"><strong><?php _e('Institución', 'aura-suite'); ?></strong></label>
                        <input type="text" class="aura-input" name="institution" id="aura-account-institution">
                    </div>
                    <div class="aura-field aura-field--span2">
                        <label for="aura-account-number" class="aura-label"><strong><?php _e('Número de cuenta (enmascarado)', 'aura-suite'); ?></strong></label>
                        <input type="text" class="aura-input" name="account_number_masked" id="aura-account-number">
                    </div>
                    <div class="aura-field">
                        <label for="aura-account-initial-balance" class="aura-label"><strong><?php _e('Saldo inicial', 'aura-suite'); ?></strong></label>
                        <input type="number" class="aura-input" step="0.01" name="initial_balance" id="aura-account-initial-balance" value="0">
                    </div>
                    <div class="aura-field">
                        <label for="aura-account-current-balance" class="aura-label"><strong><?php _e('Saldo actual', 'aura-suite'); ?></strong></label>
                        <input type="number" class="aura-input" step="0.01" name="current_balance" id="aura-account-current-balance" value="0">
                    </div>
                    <div class="aura-field aura-field--span2">
                        <label class="aura-label">
                            <input type="checkbox" name="is_active" id="aura-account-active" value="1" checked>
                            <strong><?php _e('Cuenta activa', 'aura-suite'); ?></strong>
                        </label>
                    </div>
                </div>
                <div class="aura-modal-footer aura-modal-footer-spaced">
                    <button type="button" class="btn btn-secondary btn-lift" data-modal-close="aura-finance-account-modal"><?php _e('Cancelar', 'aura-suite'); ?></button>
                    <button type="submit" class="btn btn-primary btn-shimmer btn-lift" id="aura-account-save-btn"><?php _e('Guardar cuenta', 'aura-suite'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Reembolso - Registrar Deuda -->
    <div id="aura-finance-reimburse-modal" class="aura-modal-overlay" aria-hidden="true">
        <div class="aura-modal-content aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-reimburse-modal-title">
            <div class="aura-modal-header">
                <div>
                    <h2 id="aura-reimburse-modal-title"><?php _e('Registrar Deuda / Reembolso', 'aura-suite'); ?></h2>
                    <p><?php _e('Registra una deuda a favor de un tercero o responsable.', 'aura-suite'); ?></p>
                </div>
                <button type="button" class="aura-modal-close" data-modal-close="aura-finance-reimburse-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>
            <form id="aura-reimbursements-form" class="aura-modal-body">
                <input type="hidden" id="aura-reimburse-id" value="0">
                <div class="aura-field-group cols-2">
                    <div class="aura-field">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;gap:8px;flex-wrap:wrap;">
                            <label for="aura-reimburse-person-name" class="aura-label" style="margin-bottom:0;font-weight:600;">
                                <strong><?php _e('Tercero / Responsable', 'aura-suite'); ?></strong>
                            </label>
                            <button type="button" class="btn btn-secondary btn-lift aura-btn-open-tp-explorer" 
                                    data-target-input="#aura-reimburse-person-name" 
                                    data-target-hidden="#aura-reimburse-person" 
                                    data-target-avatar="#aura-reimburse-preview-avatar"
                                    data-target-text="#aura-reimburse-preview-text"
                                    data-target-badge="#aura-reimburse-preview-badge"
                                    data-target-preview="#aura-reimburse-preview"
                                    data-format="prefixed"
                                    style="font-size:11.5px;font-weight:600;display:inline-flex;align-items:center;gap:4px;height:26px;line-height:24px;padding:0 8px;" 
                                    title="<?php esc_attr_e('Abrir catálogo y explorador clasificado por rol contable', 'aura-suite'); ?>">
                                <span class="dashicons dashicons-groups" style="font-size:13px;width:13px;height:13px;line-height:13px;"></span> <?php _e('Seleccionar Tercero', 'aura-suite'); ?>
                            </button>
                        </div>
                        <div class="aura-counterparty-autocomplete-wrap" style="position:relative;">
                            <input type="text" id="aura-reimburse-person-name" class="aura-input" autocomplete="off" placeholder="<?php esc_attr_e('Escribe o busca responsable o tercero...', 'aura-suite'); ?>" required>
                            <input type="hidden" id="aura-reimburse-person" name="person_id" value="">
                            <span class="dashicons dashicons-businessman" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;"></span>
                        </div>
                        <div id="aura-reimburse-preview" class="aura-entity-preview-card" style="display:none;margin-top:6px;align-items:center;gap:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:6px 10px;">
                            <div id="aura-reimburse-preview-avatar" class="avatar-box" style="width:28px;height:28px;border-radius:6px;overflow:hidden;flex-shrink:0;background:#e2e8f0;display:flex;align-items:center;justify-content:center;"></div>
                            <div class="entity-info" style="flex:1;min-width:0;line-height:1.2;">
                                <span id="aura-reimburse-preview-text" class="entity-name" style="font-weight:600;font-size:12px;color:#1e293b;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                                <span id="aura-reimburse-preview-badge" class="aura-pill aura-pill--muted entity-badge" style="font-size:10px;padding:1px 6px;"></span>
                            </div>
                            <button type="button" class="entity-clear-btn aura-preview-clear-btn" id="aura-reimburse-preview-clear" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:14px;padding:2px 4px;" title="<?php esc_attr_e('Quitar selección', 'aura-suite'); ?>">✕</button>
                        </div>
                    </div>
                    <div class="aura-field">
                        <label for="aura-reimburse-owed" class="aura-label"><strong><?php _e('Monto Adeudado', 'aura-suite'); ?></strong></label>
                        <input type="number" id="aura-reimburse-owed" class="aura-input" min="0.01" step="0.01" required>
                    </div>
                    <div class="aura-field">
                        <label for="aura-reimburse-origin" class="aura-label"><strong><?php _e('Transacción de Origen (opcional)', 'aura-suite'); ?></strong></label>
                        <input type="text" id="aura-reimburse-origin" class="aura-input" placeholder="ID de transacción">
                    </div>
                    <div class="aura-field aura-field--span2">
                        <label for="aura-reimburse-notes" class="aura-label"><strong><?php _e('Notas / Concepto', 'aura-suite'); ?></strong></label>
                        <textarea id="aura-reimburse-notes" class="aura-input" rows="2"></textarea>
                    </div>
                </div>
                <div class="aura-modal-footer aura-modal-footer-spaced">
                    <button type="button" class="btn btn-secondary btn-lift" data-modal-close="aura-finance-reimburse-modal"><?php _e('Cancelar', 'aura-suite'); ?></button>
                    <button type="submit" class="btn btn-primary btn-shimmer btn-lift" id="aura-reimburse-save-btn"><?php _e('Guardar Deuda', 'aura-suite'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Pago de Reembolso Enriquecido con Automatización Contable -->
    <?php
    global $wpdb;
    $pay_reimburse_cats = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}aura_finance_categories WHERE is_active = 1 ORDER BY name ASC");
    $pay_reimburse_areas = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY sort_order ASC, name ASC");
    ?>
    <div id="aura-finance-reimburse-pay-modal" class="aura-modal-overlay" aria-hidden="true">
        <div class="aura-modal-content aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-reimburse-pay-modal-title">
            <div class="aura-modal-header">
                <div>
                    <h2 id="aura-reimburse-pay-modal-title"><?php _e('Registrar Pago de Reembolso', 'aura-suite'); ?></h2>
                    <p><?php _e('Aplica el pago al beneficiario y genera automáticamente la transacción contable con su comprobante.', 'aura-suite'); ?></p>
                </div>
                <button type="button" class="aura-modal-close" data-modal-close="aura-finance-reimburse-pay-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>
            <form id="aura-reimbursements-pay-form" class="aura-modal-body" enctype="multipart/form-data">
                <input type="hidden" id="aura-reimburse-pay-id" value="0">
                <input type="hidden" id="aura-reimburse-pay-counterparty-id" value="0">
                <input type="hidden" id="aura-reimburse-pay-user-id" value="0">

                <!-- Selector de Modalidad / Origen del Reembolso -->
                <div class="aura-field-group cols-2 aura-mb-10">
                    <div class="aura-field aura-field--span2" id="aura-reimburse-pay-mode-wrap">
                        <label for="aura-reimburse-pay-debt-select" class="aura-label"><strong><?php _e('Origen del Reembolso a Pagar', 'aura-suite'); ?></strong></label>
                        <select id="aura-reimburse-pay-debt-select" class="aura-input">
                            <option value="direct"><?php _e('➕ Registrar Pago Directo (Sin registrar deuda previa)', 'aura-suite'); ?></option>
                        </select>
                    </div>

                    <!-- Buscador de Beneficiario (Tercero / Usuario WP) para Pago Directo -->
                    <div id="aura-reimburse-pay-direct-person-wrap" class="aura-field aura-field--span2" style="background: rgba(241,245,249,0.05); border: 1px dashed rgba(148,163,184,0.3); border-radius: 10px; padding: 12px 16px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;gap:8px;flex-wrap:wrap;">
                            <label for="aura-reimburse-pay-person-name" class="aura-label" style="margin-bottom:0;font-weight:600;">
                                <strong><?php _e('Beneficiario (Tercero o Usuario WP) *', 'aura-suite'); ?></strong>
                            </label>
                            <button type="button" class="btn btn-secondary btn-lift aura-btn-open-tp-explorer" 
                                    data-target-input="#aura-reimburse-pay-person-name" 
                                    data-target-hidden="#aura-reimburse-pay-person" 
                                    data-target-avatar="#aura-reimburse-pay-preview-avatar"
                                    data-target-text="#aura-reimburse-pay-preview-text"
                                    data-target-badge="#aura-reimburse-pay-preview-badge"
                                    data-target-preview="#aura-reimburse-pay-preview"
                                    data-format="prefixed"
                                    style="font-size:11.5px;font-weight:600;display:inline-flex;align-items:center;gap:4px;height:26px;line-height:24px;padding:0 8px;" 
                                    title="<?php esc_attr_e('Abrir catálogo y explorador clasificado por rol contable', 'aura-suite'); ?>">
                                <span class="dashicons dashicons-groups" style="font-size:13px;width:13px;height:13px;line-height:13px;"></span> <?php _e('Seleccionar Tercero', 'aura-suite'); ?>
                            </button>
                        </div>
                        <div class="aura-counterparty-autocomplete-wrap" style="position:relative;">
                            <input type="text" id="aura-reimburse-pay-person-name" class="aura-input" autocomplete="off" placeholder="<?php esc_attr_e('Escribe o busca responsable o tercero...', 'aura-suite'); ?>">
                            <input type="hidden" id="aura-reimburse-pay-person" name="person_id" value="">
                            <span class="dashicons dashicons-businessman" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;"></span>
                        </div>
                        <div id="aura-reimburse-pay-preview" class="aura-entity-preview-card" style="display:none;margin-top:6px;align-items:center;gap:8px;background:rgba(255,255,255,0.05);border:1px solid rgba(226,232,240,0.2);border-radius:8px;padding:6px 10px;">
                            <div id="aura-reimburse-pay-preview-avatar" class="avatar-box" style="width:28px;height:28px;border-radius:6px;overflow:hidden;flex-shrink:0;background:rgba(226,232,240,0.2);display:flex;align-items:center;justify-content:center;"></div>
                            <div class="entity-info" style="flex:1;min-width:0;line-height:1.2;">
                                <span id="aura-reimburse-pay-preview-text" class="entity-name" style="font-weight:600;font-size:12px;color:inherit;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                                <span id="aura-reimburse-pay-preview-badge" class="aura-pill aura-pill--muted entity-badge" style="font-size:10px;padding:1px 6px;"></span>
                            </div>
                            <button type="button" class="entity-clear-btn aura-preview-clear-btn" id="aura-reimburse-pay-preview-clear" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:14px;padding:2px 4px;" title="<?php esc_attr_e('Quitar selección', 'aura-suite'); ?>">✕</button>
                        </div>
                    </div>

                    <!-- Tarjeta Resumen del Reembolso (para deudas existentes) -->
                    <div id="aura-reimburse-pay-summary" class="aura-reimburse-summary-card aura-field--span2" style="display:none;">
                        <div class="aura-reimburse-summary-item">
                            <span><?php _e('Beneficiario', 'aura-suite'); ?></span>
                            <strong id="aura-reimburse-pay-sum-beneficiary">—</strong>
                        </div>
                        <div class="aura-reimburse-summary-item">
                            <span><?php _e('Total Deuda', 'aura-suite'); ?></span>
                            <strong id="aura-reimburse-pay-sum-owed" class="is-amount">—</strong>
                        </div>
                        <div class="aura-reimburse-summary-item">
                            <span><?php _e('Saldo Pendiente', 'aura-suite'); ?></span>
                            <strong id="aura-reimburse-pay-sum-remaining" class="is-remaining">—</strong>
                        </div>
                    </div>
                </div>

                <div class="aura-field-group cols-2">
                    <div class="aura-field">
                        <label for="aura-reimburse-pay-account" class="aura-label"><strong><?php _e('Cuenta Bancaria de Pago *', 'aura-suite'); ?></strong></label>
                        <select id="aura-reimburse-pay-account" class="aura-input" required>
                            <option value=""><?php _e('Seleccionar cuenta...', 'aura-suite'); ?></option>
                        </select>
                    </div>
                    <div class="aura-field">
                        <label for="aura-reimburse-pay-amount" class="aura-label"><strong><?php _e('Monto a Pagar *', 'aura-suite'); ?></strong></label>
                        <input type="number" id="aura-reimburse-pay-amount" class="aura-input" min="0.01" step="0.01" required placeholder="0.00">
                    </div>
                    <div class="aura-field">
                        <label for="aura-reimburse-pay-date" class="aura-label"><strong><?php _e('Fecha de Pago *', 'aura-suite'); ?></strong></label>
                        <input type="date" id="aura-reimburse-pay-date" class="aura-input" required value="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                    </div>
                    <div class="aura-field">
                        <label for="aura-reimburse-pay-method" class="aura-label"><strong><?php _e('Método de Pago', 'aura-suite'); ?></strong></label>
                        <select id="aura-reimburse-pay-method" class="aura-input">
                            <option value="transferencia"><?php _e('Transferencia Bancaria', 'aura-suite'); ?></option>
                            <option value="efectivo"><?php _e('Efectivo', 'aura-suite'); ?></option>
                            <option value="cheque"><?php _e('Cheque', 'aura-suite'); ?></option>
                            <option value="tarjeta"><?php _e('Tarjeta Débito/Crédito', 'aura-suite'); ?></option>
                        </select>
                    </div>

                    <!-- Caja de Automatización Contable en Libro Mayor -->
                    <div class="aura-field aura-field--span2">
                        <div class="aura-auto-tx-box">
                            <div class="aura-auto-tx-header">
                                <label for="aura-reimburse-pay-create-tx">
                                    <input type="checkbox" id="aura-reimburse-pay-create-tx" name="create_transaction" value="1" checked>
                                    <span>
                                        <strong><?php _e('Registrar Transacción de Egreso en Libro Mayor', 'aura-suite'); ?></strong>
                                        <small style="display:block;font-weight:400;color:#15803d;font-size:11.5px;">
                                            <?php _e('Crea automáticamente el asiento contable vinculando al beneficiario y descontando de la cuenta.', 'aura-suite'); ?>
                                        </small>
                                    </span>
                                </label>
                            </div>
                            <div id="aura-reimburse-pay-tx-fields" class="aura-auto-tx-fields">
                                <div class="aura-field-group cols-2">
                                    <div class="aura-field">
                                        <label for="aura-reimburse-pay-category" class="aura-label"><strong><?php _e('Categoría Contable *', 'aura-suite'); ?></strong></label>
                                        <select id="aura-reimburse-pay-category" class="aura-input">
                                            <option value=""><?php _e('Seleccionar categoría...', 'aura-suite'); ?></option>
                                            <?php if (!empty($pay_reimburse_cats)) : ?>
                                                <?php foreach ($pay_reimburse_cats as $cat) : ?>
                                                    <option value="<?php echo esc_attr($cat->id); ?>"><?php echo esc_html($cat->name); ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="aura-field">
                                        <label for="aura-reimburse-pay-area" class="aura-label"><strong><?php _e('Área / Departamento', 'aura-suite'); ?></strong></label>
                                        <select id="aura-reimburse-pay-area" class="aura-input">
                                            <option value=""><?php _e('Sin área asignada (General)', 'aura-suite'); ?></option>
                                            <?php if (!empty($pay_reimburse_areas)) : ?>
                                                <?php foreach ($pay_reimburse_areas as $ar) : ?>
                                                    <option value="<?php echo esc_attr($ar->id); ?>"><?php echo esc_html($ar->name); ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="aura-field aura-field--span2">
                                        <label for="aura-reimburse-pay-concept" class="aura-label"><strong><?php _e('Concepto de la Transacción', 'aura-suite'); ?></strong></label>
                                        <input type="text" id="aura-reimburse-pay-concept" class="aura-input" placeholder="<?php esc_attr_e('Ej: Pago de reembolso de gastos de viaje', 'aura-suite'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subida de Comprobante / Soporte Digital -->
                    <div class="aura-field aura-field--span2">
                        <div class="aura-receipt-upload-box">
                            <label for="aura-reimburse-pay-receipt" class="aura-label" style="display:flex;align-items:center;gap:6px;">
                                <span class="dashicons dashicons-media-document" style="color:#2563eb;"></span>
                                <strong><?php _e('Comprobante de Pago / Soporte Bancario (opcional)', 'aura-suite'); ?></strong>
                            </label>
                            <p style="font-size:12px;color:#64748b;margin:2px 0 8px;">
                                <?php _e('Adjunta voucher de transferencia, factura o comprobante (JPG, PNG, WEBP, PDF). Se guardará en Google Drive / Servidor y se vinculará a la transacción.', 'aura-suite'); ?>
                            </p>
                            <input type="file" id="aura-reimburse-pay-receipt" name="receipt_file" accept=".jpg,.jpeg,.png,.webp,.pdf">
                            <div id="aura-reimburse-pay-receipt-preview" style="display:none;margin-top:6px;">
                                <span class="aura-receipt-preview-pill">
                                    <span class="dashicons dashicons-paperclip"></span>
                                    <span id="aura-reimburse-pay-receipt-name"></span>
                                    <button type="button" id="aura-reimburse-pay-receipt-clear" style="background:none;border:none;cursor:pointer;color:#dc2626;font-size:14px;padding:0 4px;" title="<?php esc_attr_e('Quitar archivo', 'aura-suite'); ?>">✕</button>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Notas Adicionales -->
                    <div class="aura-field aura-field--span2">
                        <label for="aura-reimburse-pay-notes" class="aura-label"><strong><?php _e('Notas Adicionales (opcional)', 'aura-suite'); ?></strong></label>
                        <textarea id="aura-reimburse-pay-notes" class="aura-input" rows="2" placeholder="<?php esc_attr_e('Detalles internos sobre este pago...', 'aura-suite'); ?>"></textarea>
                    </div>
                </div>
                <div class="aura-modal-footer aura-modal-footer-spaced">
                    <button type="button" class="btn btn-secondary btn-lift" data-modal-close="aura-finance-reimburse-pay-modal"><?php _e('Cancelar', 'aura-suite'); ?></button>
                    <button type="submit" class="btn btn-primary btn-shimmer btn-lift" id="aura-reimburse-pay-save-btn">
                        <span class="dashicons dashicons-saved" style="margin-right:4px;vertical-align:text-bottom;"></span>
                        <?php _e('Aplicar Pago y Contabilizar', 'aura-suite'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Nuevo Traspaso entre Cuentas -->
    <div id="aura-finance-transfer-modal" class="aura-modal-overlay" aria-hidden="true">
        <div class="aura-modal-content aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-transfer-modal-title" style="max-width: 640px;">
            <div class="aura-modal-header">
                <div>
                    <h2 id="aura-transfer-modal-title" class="aura-title-with-help">
                        <span class="dashicons dashicons-randomize" style="margin-right: 6px; color: #0d9488;"></span>
                        <?php _e('Nuevo Traspaso entre Cuentas', 'aura-suite'); ?>
                    </h2>
                    <p class="description" style="margin: 2px 0 0; font-size: 13px;">
                        <?php _e('Mueve fondos entre cuentas bancarias o fondea tu Caja Chica sin generar falsos gastos ni alterar el P&L.', 'aura-suite'); ?>
                    </p>
                </div>
                <button type="button" class="aura-modal-close" data-modal-close="aura-finance-transfer-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>
            <form id="aura-transfer-form" class="aura-modal-body" style="padding-top: 16px;">
                <!-- Callout explicativo -->
                <div style="margin-bottom: 14px; padding: 10px 14px; border-radius: 8px; font-size: 12.5px; line-height: 1.5; background: rgba(13, 148, 136, 0.08); border: 1px solid rgba(13, 148, 136, 0.25); color: #0f766e;">
                    <span class="dashicons dashicons-info" style="font-size: 16px; margin-right: 4px; vertical-align: text-bottom; color: #0d9488;"></span>
                    <strong><?php _e('Movimiento de Tesorería Puro:', 'aura-suite'); ?></strong> <?php _e('Se debitará de la cuenta origen y se acreditará en la cuenta destino simultáneamente.', 'aura-suite'); ?>
                </div>

                <!-- Cuentas Origen y Destino -->
                <div class="aura-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div class="aura-field">
                        <label for="aura-transfer-source" class="aura-label">
                            <strong style="color: #ef4444;">📤 <?php _e('Cuenta Origen (Salida) *', 'aura-suite'); ?></strong>
                        </label>
                        <select id="aura-transfer-source" name="source_account_id" class="aura-input aura-select" required>
                            <option value=""><?php _e('Seleccione cuenta origen...', 'aura-suite'); ?></option>
                        </select>
                        <div id="aura-transfer-source-balance-tip" style="font-size: 11.5px; color: #64748b; margin-top: 4px;">
                            <?php _e('Saldo disponible:', 'aura-suite'); ?> <strong id="aura-transfer-source-balance">—</strong>
                        </div>
                    </div>
                    <div class="aura-field">
                        <label for="aura-transfer-target" class="aura-label">
                            <strong style="color: #10b981;">📥 <?php _e('Cuenta Destino (Entrada) *', 'aura-suite'); ?></strong>
                        </label>
                        <select id="aura-transfer-target" name="destination_account_id" class="aura-input aura-select" required>
                            <option value=""><?php _e('Seleccione cuenta destino...', 'aura-suite'); ?></option>
                        </select>
                        <div id="aura-transfer-target-balance-tip" style="font-size: 11.5px; color: #64748b; margin-top: 4px;">
                            <?php _e('Saldo actual:', 'aura-suite'); ?> <strong id="aura-transfer-target-balance">—</strong>
                        </div>
                    </div>
                </div>

                <!-- Montos Paralelos Inteligentes (Salida vs Entrada) -->
                <div class="aura-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
                    <div class="aura-field">
                        <label for="aura-transfer-amount" class="aura-label">
                            <strong style="color: #ef4444;">📤 <?php _e('Monto que Sale (Origen) *', 'aura-suite'); ?></strong>
                            <span class="badge badge-rose" id="aura-transfer-source-currency-badge" style="margin-left: 4px; font-weight: 700; font-size: 11px;">—</span>
                        </label>
                        <div style="position: relative;">
                            <input type="number" id="aura-transfer-amount" name="amount" class="aura-input" step="0.01" min="0.01" placeholder="0.00" required style="font-size: 16px; font-weight: 700; height: 42px; padding-right: 55px;">
                            <span id="aura-transfer-source-currency-addon" style="position: absolute; right: 12px; top: 11px; font-weight: 700; color: #94a3b8; font-size: 12px;">—</span>
                        </div>
                    </div>
                    <div class="aura-field">
                        <label for="aura-transfer-dest-amount" class="aura-label">
                            <strong style="color: #10b981;">📥 <?php _e('Monto que Entra (Destino) *', 'aura-suite'); ?></strong>
                            <span class="badge badge-emerald" id="aura-transfer-dest-currency-badge" style="margin-left: 4px; font-weight: 700; font-size: 11px;">—</span>
                        </label>
                        <div style="position: relative;">
                            <input type="number" id="aura-transfer-dest-amount" name="destination_amount" class="aura-input" step="0.01" min="0.01" placeholder="0.00" required style="font-size: 16px; font-weight: 700; height: 42px; padding-right: 55px;">
                            <span id="aura-transfer-dest-currency-addon" style="position: absolute; right: 12px; top: 11px; font-weight: 700; color: #94a3b8; font-size: 12px;">—</span>
                        </div>
                    </div>
                </div>

                <!-- Barra Informativa de Tasa / Sincronización Inteligente -->
                <div id="aura-transfer-rate-box" style="margin-bottom: 14px; padding: 10px 14px; border-radius: 8px; font-size: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; background: rgba(0,0,0,0.02); border: 1px solid #e2e8f0;">
                    <div id="aura-transfer-rate-text" style="color: #64748b; display: flex; align-items: center; gap: 6px;">
                        <span class="dashicons dashicons-yes-alt" style="color: #10b981;"></span>
                        <span><?php _e('Misma moneda: montos sincronizados 1 a 1 automáticamente.', 'aura-suite'); ?></span>
                    </div>
                    <div id="aura-transfer-fx-rate-custom" style="display: none; align-items: center; gap: 6px;">
                        <label for="aura-transfer-exchange-rate" style="font-size: 11.5px; color: #b45309; font-weight: 600;">
                            <?php _e('Tasa de cambio:', 'aura-suite'); ?>
                        </label>
                        <input type="number" id="aura-transfer-exchange-rate" name="exchange_rate" class="aura-input" step="0.0001" min="0.0001" value="1.0000" style="width: 95px; height: 28px; padding: 2px 6px; font-size: 12px; font-weight: 700; background: #fff;">
                    </div>
                </div>

                <!-- Fecha de Operación y Referencia Bancaria -->
                <div class="aura-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div class="aura-field">
                        <label for="aura-transfer-date" class="aura-label">
                            <strong>📅 <?php _e('Fecha de Operación *', 'aura-suite'); ?></strong>
                        </label>
                        <input type="date" id="aura-transfer-date" name="transfer_date" class="aura-input" value="<?php echo esc_attr( current_time('Y-m-d') ); ?>" required style="height: 40px;">
                    </div>
                    <div class="aura-field">
                        <label for="aura-transfer-reference" class="aura-label">
                            <strong><?php _e('Referencia / Folio Bancario (opcional)', 'aura-suite'); ?></strong>
                        </label>
                        <input type="text" id="aura-transfer-reference" name="reference" class="aura-input" placeholder="<?php esc_attr_e('Ej: SPEI-984321, Voucher 044, Ref...', 'aura-suite'); ?>" style="height: 40px;">
                    </div>
                </div>

                <div class="aura-field" style="margin-bottom: 14px;">
                    <label for="aura-transfer-notes" class="aura-label">
                        <strong><?php _e('Concepto o Notas del Traspaso', 'aura-suite'); ?></strong>
                    </label>
                    <textarea id="aura-transfer-notes" name="notes" class="aura-input" rows="2" placeholder="<?php esc_attr_e('Ej: Fondeo de Caja Chica semanal para gastos operativos menores, traspaso a cuenta de ahorros...', 'aura-suite'); ?>"></textarea>
                </div>

                <div class="aura-modal-footer aura-modal-footer-spaced aura-modal-footer-end" style="margin-top: 16px; padding: 0;">
                    <button type="button" class="btn btn-secondary btn-lift" data-modal-close="aura-finance-transfer-modal"><?php _e('Cancelar', 'aura-suite'); ?></button>
                    <button type="submit" class="btn btn-primary btn-shimmer btn-lift" id="aura-transfer-submit-btn" style="background:#0d9488;">
                        <span class="dashicons dashicons-saved" style="margin-right: 4px; vertical-align: text-bottom;"></span>
                        <?php _e('Ejecutar Traspaso', 'aura-suite'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detalle de Traspaso -->
    <div id="aura-finance-transfer-detail-modal" class="aura-modal-overlay" aria-hidden="true">
        <div class="aura-modal-content aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-transfer-detail-title" style="max-width: 600px;">
            <div class="aura-modal-header">
                <div>
                    <h2 id="aura-transfer-detail-title" class="aura-title-with-help">
                        <span class="dashicons dashicons-media-document" style="margin-right: 6px; color: #0d9488;"></span>
                        <span id="aura-transfer-detail-code">TRF-XXXXXX</span>
                    </h2>
                    <p class="description" style="margin: 2px 0 0; font-size: 13px;">
                        <?php _e('Comprobante y trazabilidad de movimiento entre cuentas.', 'aura-suite'); ?>
                    </p>
                </div>
                <button type="button" class="aura-modal-close" data-modal-close="aura-finance-transfer-detail-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>
            <div class="aura-modal-body" style="padding-top: 16px;" id="aura-transfer-detail-body">
                <!-- Se inyecta dinámicamente con JS -->
            </div>
            <div class="aura-modal-footer aura-modal-footer-spaced">
                <button type="button" class="btn btn-danger btn-lift" id="aura-transfer-detail-cancel-btn" style="display:none;">
                    <span class="dashicons dashicons-undo" style="vertical-align:text-bottom; margin-right:4px;"></span>
                    <?php _e('Anular este Traspaso', 'aura-suite'); ?>
                </button>
                <button type="button" class="btn btn-secondary btn-lift" data-modal-close="aura-finance-transfer-detail-modal" style="margin-left:auto;">
                    <?php _e('Cerrar', 'aura-suite'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Anular Traspaso -->
    <div id="aura-finance-transfer-cancel-modal" class="aura-modal-overlay" aria-hidden="true">
        <div class="aura-modal-content aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-transfer-cancel-title" style="max-width: 500px;">
            <div class="aura-modal-header">
                <div>
                    <h2 id="aura-transfer-cancel-title" class="aura-title-with-help" style="color: #dc2626;">
                        <span class="dashicons dashicons-undo" style="margin-right: 6px;"></span>
                        <?php _e('Anular Traspaso', 'aura-suite'); ?>
                    </h2>
                    <p class="description" style="margin: 2px 0 0; font-size: 13px;">
                        <?php _e('Se devolverá el dinero a la cuenta de origen y se debitará de la cuenta de destino.', 'aura-suite'); ?>
                    </p>
                </div>
                <button type="button" class="aura-modal-close" data-modal-close="aura-finance-transfer-cancel-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>
            <form id="aura-transfer-cancel-form" class="aura-modal-body" style="padding-top: 16px;">
                <input type="hidden" id="aura-transfer-cancel-id" name="id" value="0">
                <div id="aura-transfer-cancel-summary" style="margin-bottom: 14px; padding: 10px 14px; border-radius: 8px; font-size: 12.5px; line-height: 1.5; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); color: #991b1b;"></div>
                <div class="aura-field">
                    <label for="aura-transfer-cancel-reason" class="aura-label"><strong style="color: #dc2626;"><?php _e('Motivo de la Anulación *', 'aura-suite'); ?></strong></label>
                    <textarea id="aura-transfer-cancel-reason" class="aura-input" name="cancel_reason" rows="3" required placeholder="<?php esc_attr_e('Ej: Traspaso registrado por error, monto duplicado...', 'aura-suite'); ?>"></textarea>
                </div>
                <div class="aura-modal-footer aura-modal-footer-spaced aura-modal-footer-end" style="margin-top: 16px; padding: 0;">
                    <button type="button" class="btn btn-secondary btn-lift" data-modal-close="aura-finance-transfer-cancel-modal"><?php _e('Cerrar', 'aura-suite'); ?></button>
                    <button type="submit" class="btn btn-danger btn-shimmer btn-lift" id="aura-transfer-cancel-submit-btn"><?php _e('Confirmar y Revertir Saldos', 'aura-suite'); ?></button>
                </div>
            </form>
        </div>
    </div>

</div> <!-- END aura-app-context -->
</div> <!-- END aura-app-wrapper -->
