<?php
/**
 * Template: Bancos y Cuentas
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap aura-financial-accounts-page">
    <div class="aura-accounts-hero">
        <div>
            <h1 class="wp-heading-inline aura-title-with-help"><?php _e('Bancos y Cuentas', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Aquí ves saldos, movimientos clave y accesos rápidos. Usa los botones de arriba para registrar y esta pantalla para revisar.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h1>
            <p class="description aura-accounts-hero__text">
                <?php _e('Administra dónde está el dinero, monitorea saldos por moneda y define topes presupuestales desde una sola pantalla.', 'aura-suite'); ?>
            </p>
        </div>
    </div>

    <div class="aura-accounts-kpis" aria-label="Resumen de bancos">
        <article class="aura-accounts-kpi-card">
            <span class="dashicons dashicons-bank aura-accounts-kpi-card__icon"></span>
            <div>
                <span class="aura-accounts-kpi-card__label"><?php _e('Cuentas registradas', 'aura-suite'); ?></span>
                <strong id="aura-kpi-total-accounts" class="aura-accounts-kpi-card__value">0</strong>
            </div>
        </article>
        <article class="aura-accounts-kpi-card">
            <span class="dashicons dashicons-yes-alt aura-accounts-kpi-card__icon is-success"></span>
            <div>
                <span class="aura-accounts-kpi-card__label"><?php _e('Cuentas activas', 'aura-suite'); ?></span>
                <strong id="aura-kpi-active-accounts" class="aura-accounts-kpi-card__value">0</strong>
            </div>
        </article>
        <article class="aura-accounts-kpi-card">
            <span class="dashicons dashicons-money-alt aura-accounts-kpi-card__icon is-primary"></span>
            <div>
                <span class="aura-accounts-kpi-card__label"><?php _e('Saldo total', 'aura-suite'); ?></span>
                <strong id="aura-kpi-balance-cop" class="aura-accounts-kpi-card__value">0.00</strong>
            </div>
        </article>
        <article class="aura-accounts-kpi-card">
            <span class="dashicons dashicons-money aura-accounts-kpi-card__icon is-accent"></span>
            <div>
                <span class="aura-accounts-kpi-card__label"><?php _e('Saldo total USD', 'aura-suite'); ?></span>
                <strong id="aura-kpi-balance-usd" class="aura-accounts-kpi-card__value">0.00</strong>
            </div>
        </article>
    </div>

    <div id="aura-accounts-feedback" class="notice" style="display:none;"></div>

    <div class="aura-accounts-list-card">
        <div class="aura-finance-primary-actions">
            <button type="button" class="aura-action-button aura-action-button--primary" id="aura-account-new-btn">
                <span class="dashicons dashicons-plus-alt2"></span>
                <div>
                    <strong><?php _e('Nueva Cuenta', 'aura-suite'); ?></strong>
                    <span><?php _e('Banco, caja o aporte', 'aura-suite'); ?></span>
                </div>
            </button>
            <button type="button" class="aura-action-button aura-action-button--secondary" id="aura-budget-open-btn">
                <span class="dashicons dashicons-chart-area"></span>
                <div>
                    <strong><?php _e('Configurar presupuesto', 'aura-suite'); ?></strong>
                    <span><?php _e('Tope e importación', 'aura-suite'); ?></span>
                </div>
            </button>
            <button type="button" class="aura-action-button aura-action-button--secondary" id="aura-petty-open-btn">
                <span class="dashicons dashicons-open-folder"></span>
                <div>
                    <strong><?php _e('Nueva entrega de caja chica', 'aura-suite'); ?></strong>
                    <span><?php _e('Entrega y rendición', 'aura-suite'); ?></span>
                </div>
            </button>
            <button type="button" class="aura-action-button aura-action-button--secondary" id="aura-reimburse-open-btn">
                <span class="dashicons dashicons-money"></span>
                <div>
                    <strong><?php _e('Registrar deuda', 'aura-suite'); ?></strong>
                    <span><?php _e('Gasto de bolsillo', 'aura-suite'); ?></span>
                </div>
            </button>
            <button type="button" class="aura-action-button aura-action-button--secondary" id="aura-reimburse-pay-open-btn">
                <span class="dashicons dashicons-arrow-right-alt"></span>
                <div>
                    <strong><?php _e('Registrar pago', 'aura-suite'); ?></strong>
                    <span><?php _e('De una deuda', 'aura-suite'); ?></span>
                </div>
            </button>
        </div>



        <div class="aura-card-head">
            <div>
                <h2 class="aura-title-with-help"><?php _e('Listado de cuentas', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Filtra por tipo, moneda y estado. Desde Acciones puedes editar o eliminar una cuenta existente.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h2>
                <p><?php _e('Consulta bancos, cajas menores, aportes y Caja USD con filtros rápidos.', 'aura-suite'); ?></p>
            </div>
        </div>

        <div class="aura-accounts-toolbar">
            <div class="aura-accounts-toolbar__search">
                <span class="dashicons dashicons-search"></span>
                <input type="search" id="aura-accounts-search" placeholder="<?php esc_attr_e('Buscar por cuenta, tipo, moneda o institución...', 'aura-suite'); ?>">
            </div>
            <div class="aura-accounts-filters" id="aura-accounts-filter-bar">
                <select id="aura-filter-type" class="aura-filter-select" aria-label="<?php esc_attr_e('Filtrar por tipo', 'aura-suite'); ?>">
                    <option value=""><?php _e('Todos los tipos', 'aura-suite'); ?></option>
                    <option value="bank_account"><?php _e('Cuenta Bancaria', 'aura-suite'); ?></option>
                    <option value="petty_cash"><?php _e('Caja Chica', 'aura-suite'); ?></option>
                    <option value="contributions_fund"><?php _e('Aportes', 'aura-suite'); ?></option>
                    <option value="usd_cash"><?php _e('Caja USD', 'aura-suite'); ?></option>
                    <option value="custom"><?php _e('Otro', 'aura-suite'); ?></option>
                </select>
                <select id="aura-filter-currency" class="aura-filter-select" aria-label="<?php esc_attr_e('Filtrar por moneda', 'aura-suite'); ?>">
                    <option value=""><?php _e('Todas las monedas', 'aura-suite'); ?></option>
                </select>
                <select id="aura-filter-status" class="aura-filter-select" aria-label="<?php esc_attr_e('Filtrar por estado', 'aura-suite'); ?>">
                    <option value=""><?php _e('Todos los estados', 'aura-suite'); ?></option>
                    <option value="1"><?php _e('Activa', 'aura-suite'); ?></option>
                    <option value="0"><?php _e('Inactiva', 'aura-suite'); ?></option>
                </select>
                <button type="button" id="aura-filter-reset" class="aura-filter-reset-btn" style="display:none" aria-label="<?php esc_attr_e('Limpiar filtros', 'aura-suite'); ?>">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php _e('Limpiar', 'aura-suite'); ?>
                </button>
                <span id="aura-filter-count" class="aura-filter-active-count" style="display:none"></span>
            </div>
        </div>

        <div class="aura-accounts-table-wrap">
            <table class="widefat striped display nowrap" id="aura-accounts-table" style="width:100%">
                <thead>
                    <tr>
                        <th><?php _e('Cuenta', 'aura-suite'); ?></th>
                        <th><?php _e('Tipo', 'aura-suite'); ?></th>
                        <th><?php _e('Moneda', 'aura-suite'); ?></th>
                        <th><?php _e('Saldo actual', 'aura-suite'); ?></th>
                        <th><?php _e('Estado', 'aura-suite'); ?></th>
                        <th><?php _e('Acciones', 'aura-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6"><?php _e('Cargando cuentas...', 'aura-suite'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="aura-budget-card">
        <div class="aura-card-head aura-card-head--budget">
            <div>
                <h2 class="aura-title-with-help"><?php _e('Presupuesto Anual y Mensual', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Compara tope anual contra distribución mensual. Si algo no cuadra, ajusta desde el modal de presupuesto.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h2>
                <p><?php _e('Define el techo anual, distribuye por mes y detecta desbalances.', 'aura-suite'); ?></p>
            </div>
        </div>

        <div class="aura-budget-overview">
            <article class="aura-budget-overview-card">
                <span class="aura-budget-overview-card__label"><?php _e('Tope anual', 'aura-suite'); ?></span>
                <strong id="aura-budget-kpi-annual" class="aura-budget-overview-card__value">0.00</strong>
            </article>
            <article class="aura-budget-overview-card">
                <span class="aura-budget-overview-card__label"><?php _e('Suma mensual', 'aura-suite'); ?></span>
                <strong id="aura-budget-kpi-monthly" class="aura-budget-overview-card__value">0.00</strong>
            </article>
            <article class="aura-budget-overview-card">
                <span class="aura-budget-overview-card__label"><?php _e('Disponible restante', 'aura-suite'); ?></span>
                <strong id="aura-budget-kpi-remaining" class="aura-budget-overview-card__value">0.00</strong>
            </article>
            <article class="aura-budget-overview-card">
                <span class="aura-budget-overview-card__label"><?php _e('Política activa', 'aura-suite'); ?></span>
                <strong id="aura-budget-kpi-policy" class="aura-budget-overview-card__value"><?php _e('Advertir', 'aura-suite'); ?></strong>
            </article>
        </div>

        <div class="aura-budget-progress-panel">
            <div class="aura-budget-progress-panel__head">
                <span><?php _e('Distribución programada', 'aura-suite'); ?></span>
                <strong id="aura-budget-progress-text">0%</strong>
            </div>
            <div class="aura-budget-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <span id="aura-budget-progress-bar" class="aura-budget-progress-fill" style="width:0%"></span>
            </div>
        </div>

        <div class="aura-budget-cta-box">
            <button type="button" class="button button-primary" id="aura-budget-open-inline-btn"><?php _e('Configurar presupuesto', 'aura-suite'); ?></button>
        </div>
    </div>

    <div class="aura-petty-cash-card">
        <div class="aura-card-head aura-card-head--petty">
            <div>
                <h2 class="aura-title-with-help"><?php _e('Caja Chica: Entrega y Rendición', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Primero registra la entrega y luego completa la rendición. La tabla te ayuda a controlar vencimientos y estado.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h2>
                <p><?php _e('Inicia una entrega de fondo, registra gasto/devolución y envía la rendición para aprobación.', 'aura-suite'); ?></p>
            </div>
            <div class="aura-section-actions">
                <button type="button" class="button button-primary" id="aura-petty-open-inline-btn"><?php _e('Nueva entrega / rendición', 'aura-suite'); ?></button>
            </div>
        </div>



        <div class="aura-petty-cash-table-wrap">
            <table class="widefat striped" id="aura-petty-cash-table">
                <thead>
                    <tr>
                        <th><?php _e('Fecha', 'aura-suite'); ?></th>
                        <th><?php _e('Vence', 'aura-suite'); ?></th>
                        <th><?php _e('Cuenta', 'aura-suite'); ?></th>
                        <th><?php _e('Responsable', 'aura-suite'); ?></th>
                        <th><?php _e('Entregado', 'aura-suite'); ?></th>
                        <th><?php _e('Gastado', 'aura-suite'); ?></th>
                        <th><?php _e('Devuelto', 'aura-suite'); ?></th>
                        <th><?php _e('Estado', 'aura-suite'); ?></th>
                        <th><?php _e('Acciones', 'aura-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="9"><?php _e('Cargando rendiciones...', 'aura-suite'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="aura-petty-evidence-modal" class="aura-petty-evidence-modal" style="display:none;" aria-hidden="true">
            <div class="aura-petty-evidence-modal__backdrop" data-close="1"></div>
            <div class="aura-petty-evidence-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="aura-petty-evidence-title">
                <div class="aura-petty-evidence-modal__head">
                    <h3 id="aura-petty-evidence-title"><?php _e('Evidencias de la rendición', 'aura-suite'); ?></h3>
                    <button type="button" class="button-link" id="aura-petty-evidence-close" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
                </div>
                <div id="aura-petty-evidence-content" class="aura-petty-evidence-modal__content"></div>
            </div>
        </div>
    </div>

    <div class="aura-reimburse-card">
        <div class="aura-card-head aura-card-head--reimburse">
            <div>
                <h2 class="aura-title-with-help"><?php _e('Reembolsos a Personas', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Registra deudas por gastos personales y aplica pagos parciales o totales desde cuentas reales.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h2>
                <p><?php _e('Controla deudas por gastos pagados con dinero personal y registra pagos parciales o totales desde cuentas reales.', 'aura-suite'); ?></p>
            </div>
            <div class="aura-section-actions">
                <button type="button" class="button button-primary" id="aura-reimburse-open-inline-btn"><?php _e('Registrar deuda', 'aura-suite'); ?></button>
                <button type="button" class="button" id="aura-reimburse-pay-open-inline-btn"><?php _e('Registrar pago', 'aura-suite'); ?></button>
            </div>
        </div>



        <div class="aura-reimburse-table-wrap">
            <table class="widefat striped" id="aura-reimbursements-table">
                <thead>
                    <tr>
                        <th><?php _e('Fecha', 'aura-suite'); ?></th>
                        <th><?php _e('Persona', 'aura-suite'); ?></th>
                        <th><?php _e('Origen', 'aura-suite'); ?></th>
                        <th><?php _e('Adeudado', 'aura-suite'); ?></th>
                        <th><?php _e('Pagado', 'aura-suite'); ?></th>
                        <th><?php _e('Pendiente', 'aura-suite'); ?></th>
                        <th><?php _e('Estado', 'aura-suite'); ?></th>
                        <th><?php _e('Acciones', 'aura-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8"><?php _e('Cargando reembolsos...', 'aura-suite'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="aura-finance-account-modal" class="aura-finance-modal" style="display:none;" aria-hidden="true">
        <div class="aura-finance-modal__backdrop" data-modal-close="aura-finance-account-modal"></div>
        <div class="aura-finance-modal__dialog aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-account-form-title">
            <div class="aura-finance-modal__head">
                <div>
                    <h2 id="aura-account-form-title" class="aura-title-with-help"><?php _e('Nueva Cuenta', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Completa tipo, moneda y saldos base. El saldo actual debe reflejar el valor operativo real.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h2>
                    <p><?php _e('Crea o edita cuentas financieras con datos operativos y saldo base.', 'aura-suite'); ?></p>
                </div>
                <button type="button" class="button-link" data-modal-close="aura-finance-account-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>

            <form id="aura-account-form" class="aura-finance-modal__form">
                <input type="hidden" name="id" id="aura-account-id" value="0">

                <div class="aura-finance-form-grid aura-finance-form-grid--two">
                    <p>
                        <label for="aura-account-name"><strong><?php _e('Nombre', 'aura-suite'); ?></strong></label>
                        <input type="text" class="regular-text" name="name" id="aura-account-name" required>
                    </p>

                    <p>
                        <label for="aura-account-type"><strong><?php _e('Tipo de cuenta', 'aura-suite'); ?></strong></label>
                        <select name="account_type" id="aura-account-type" class="regular-text">
                            <option value="bank_account"><?php _e('Cuenta Bancaria', 'aura-suite'); ?></option>
                            <option value="petty_cash"><?php _e('Caja Chica', 'aura-suite'); ?></option>
                            <option value="contributions_fund"><?php _e('Aportes', 'aura-suite'); ?></option>
                            <option value="usd_cash"><?php _e('Caja USD', 'aura-suite'); ?></option>
                            <option value="custom"><?php _e('Otro', 'aura-suite'); ?></option>
                        </select>
                    </p>

                    <p>
                        <label for="aura-account-currency"><strong><?php _e('Moneda', 'aura-suite'); ?></strong></label>
                        <input type="text" class="small-text" name="currency" id="aura-account-currency" value="COP" maxlength="10">
                    </p>

                    <p>
                        <label for="aura-account-institution"><strong><?php _e('Entidad/Institución', 'aura-suite'); ?></strong></label>
                        <input type="text" class="regular-text" name="institution" id="aura-account-institution">
                    </p>

                    <p>
                        <label for="aura-account-number"><strong><?php _e('Número (enmascarado)', 'aura-suite'); ?></strong></label>
                        <input type="text" class="regular-text" name="account_number_masked" id="aura-account-number" placeholder="****1234">
                    </p>

                    <p>
                        <label for="aura-account-initial-balance"><strong><?php _e('Saldo inicial', 'aura-suite'); ?></strong></label>
                        <input type="number" step="0.01" name="initial_balance" id="aura-account-initial-balance" value="0">
                    </p>

                    <p>
                        <label for="aura-account-current-balance"><strong><?php _e('Saldo actual', 'aura-suite'); ?></strong></label>
                        <input type="number" step="0.01" name="current_balance" id="aura-account-current-balance" value="0">
                    </p>
                </div>

                <p>
                    <label>
                        <input type="checkbox" name="is_active" id="aura-account-active" checked>
                        <?php _e('Cuenta activa', 'aura-suite'); ?>
                    </label>
                </p>

                <p class="submit">
                    <button type="submit" class="button button-primary" id="aura-account-save-btn"><?php _e('Guardar cuenta', 'aura-suite'); ?></button>
                    <button type="button" class="button" id="aura-account-reset-btn"><?php _e('Limpiar', 'aura-suite'); ?></button>
                </p>
            </form>
        </div>
    </div>

    <div id="aura-finance-budget-modal" class="aura-finance-modal" style="display:none;" aria-hidden="true">
        <div class="aura-finance-modal__backdrop" data-modal-close="aura-finance-budget-modal"></div>
        <div class="aura-finance-modal__dialog aura-finance-modal__dialog--large" role="dialog" aria-modal="true" aria-labelledby="aura-budget-modal-title">
            <div class="aura-finance-modal__head">
                <div>
                    <h2 id="aura-budget-modal-title" class="aura-title-with-help"><?php _e('Presupuesto anual y mensual', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Paso 1: define año, tope e importación opcional. Paso 2: revisa distribución y guarda.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h2>
                </div>
                <button type="button" class="button-link" data-modal-close="aura-finance-budget-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>

            <form id="aura-budget-form" class="aura-finance-modal__form">
                <div class="aura-modal-wizard" id="aura-budget-modal-wizard">
                    <div class="aura-modal-wizard__steps">
                        <span class="aura-modal-step is-active" data-step="1"><?php _e('1. Base e importación', 'aura-suite'); ?></span>
                        <span class="aura-modal-step" data-step="2"><?php _e('2. Distribución y guardado', 'aura-suite'); ?></span>
                    </div>

                    <section class="aura-modal-wizard__panel is-active" data-step="1">
                        <div class="aura-budget-grid">
                            <p>
                                <label for="aura-budget-year"><strong><?php _e('Año fiscal', 'aura-suite'); ?></strong></label>
                                <input type="number" id="aura-budget-year" name="year" min="2000" max="2100" value="<?php echo esc_attr((int) current_time('Y')); ?>" required>
                            </p>

                            <p>
                                <label for="aura-budget-annual-limit"><strong><?php _e('Tope anual', 'aura-suite'); ?></strong></label>
                                <input type="number" id="aura-budget-annual-limit" name="annual_limit" min="0" step="0.01" value="0" required>
                            </p>

                            <p>
                                <label for="aura-budget-policy"><strong><?php _e('Política al exceder', 'aura-suite'); ?></strong></label>
                                <select id="aura-budget-policy" name="exceed_policy">
                                    <option value="warn"><?php _e('Advertir y permitir guardar', 'aura-suite'); ?></option>
                                    <option value="block"><?php _e('Bloquear guardado', 'aura-suite'); ?></option>
                                </select>
                            </p>
                        </div>

                        <div class="aura-budget-import-box">
                            <h3><?php _e('Importar desde Excel/CSV', 'aura-suite'); ?></h3>
                            <p class="description"><?php _e('Usa tu plantilla para crear o actualizar presupuestos por año de forma masiva.', 'aura-suite'); ?></p>
                            <div class="aura-budget-import-actions">
                                <a
                                    href="<?php echo esc_url(admin_url('admin-ajax.php?action=aura_finance_budget_template&nonce=' . wp_create_nonce('aura_financial_accounts_nonce'))); ?>"
                                    class="button"
                                    id="aura-budget-download-template">
                                    <?php _e('Descargar plantilla', 'aura-suite'); ?>
                                </a>
                                <input type="file" id="aura-budget-import-file" accept=".csv,.xlsx">
                                <button type="button" class="button button-secondary" id="aura-budget-import-btn"><?php _e('1) Analizar archivo', 'aura-suite'); ?></button>
                            </div>
                            <p class="description"><?php _e('Columnas esperadas: year, annual_limit, exceed_policy, jan..dec (también acepta nombres de meses en español).', 'aura-suite'); ?></p>

                            <div id="aura-budget-import-wizard" style="display:none; margin-top:10px;">
                                <div id="aura-budget-import-summary" class="aura-budget-import-summary"></div>

                                <div class="aura-budget-mapping-wrap">
                                    <h4><?php _e('Mapeo de columnas', 'aura-suite'); ?></h4>
                                    <p class="description"><?php _e('Asigna cada campo del sistema a una columna de tu archivo. Año fiscal y tope anual son obligatorios.', 'aura-suite'); ?></p>
                                    <div class="aura-budget-mapping-grid" id="aura-budget-mapping-grid"></div>
                                </div>

                                <div class="aura-budget-import-preview-wrap">
                                    <h4><?php _e('Vista previa (primeras filas)', 'aura-suite'); ?></h4>
                                    <div class="aura-budget-import-table-wrap">
                                        <table class="widefat striped" id="aura-budget-preview-table">
                                            <thead></thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="aura-budget-import-wizard-actions">
                                    <button type="button" class="button" id="aura-budget-validate-btn"><?php _e('2) Validar datos', 'aura-suite'); ?></button>
                                    <button type="button" class="button button-primary" id="aura-budget-confirm-btn" disabled><?php _e('3) Confirmar importación', 'aura-suite'); ?></button>
                                </div>

                                <div id="aura-budget-import-validation" class="aura-budget-import-validation" style="display:none;"></div>
                            </div>
                        </div>

                        <div class="aura-modal-wizard__actions">
                            <button type="button" class="button button-primary" id="aura-budget-step-next"><?php _e('Continuar a distribución', 'aura-suite'); ?></button>
                        </div>
                    </section>

                    <section class="aura-modal-wizard__panel" data-step="2">
                        <h3><?php _e('Distribución mensual', 'aura-suite'); ?></h3>
                        <div class="aura-budget-months-grid">
                            <?php
                            $month_labels = array(
                                __('Ene', 'aura-suite'), __('Feb', 'aura-suite'), __('Mar', 'aura-suite'), __('Abr', 'aura-suite'),
                                __('May', 'aura-suite'), __('Jun', 'aura-suite'), __('Jul', 'aura-suite'), __('Ago', 'aura-suite'),
                                __('Sep', 'aura-suite'), __('Oct', 'aura-suite'), __('Nov', 'aura-suite'), __('Dic', 'aura-suite')
                            );
                            foreach ($month_labels as $idx => $month_label) :
                                $month_num = $idx + 1;
                            ?>
                            <div class="aura-budget-month-card">
                                <label for="aura-budget-month-<?php echo esc_attr($month_num); ?>"><strong><?php echo esc_html($month_label); ?></strong></label>
                                <input
                                    type="number"
                                    id="aura-budget-month-<?php echo esc_attr($month_num); ?>"
                                    class="aura-budget-month-input"
                                    min="0"
                                    step="0.01"
                                    value="0"
                                    data-month="<?php echo esc_attr($month_num); ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <p class="aura-budget-summary">
                            <strong><?php _e('Suma mensual:', 'aura-suite'); ?></strong>
                            <span id="aura-budget-monthly-total">0.00</span>
                        </p>

                        <div class="aura-modal-wizard__actions">
                            <button type="button" class="button" id="aura-budget-step-back"><?php _e('Volver', 'aura-suite'); ?></button>
                            <button type="button" class="button" id="aura-budget-load-btn"><?php _e('Recargar año', 'aura-suite'); ?></button>
                            <button type="submit" class="button button-primary" id="aura-budget-save-btn"><?php _e('Guardar presupuesto', 'aura-suite'); ?></button>
                        </div>
                    </section>
                </div>
            </form>
        </div>
    </div>

    <div id="aura-finance-petty-modal" class="aura-finance-modal" style="display:none;" aria-hidden="true">
        <div class="aura-finance-modal__backdrop" data-modal-close="aura-finance-petty-modal"></div>
        <div class="aura-finance-modal__dialog aura-finance-modal__dialog--large" role="dialog" aria-modal="true" aria-labelledby="aura-petty-modal-title">
            <div class="aura-finance-modal__head">
                <div>
                    <h2 id="aura-petty-modal-title" class="aura-title-with-help"><?php _e('Caja chica: entrega y rendición', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Paso 1: datos de entrega. Paso 2: rendición con evidencias. Paso 3: confirma y envía.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h2>
                </div>
                <button type="button" class="button-link" data-modal-close="aura-finance-petty-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>

            <form id="aura-petty-cash-form" class="aura-petty-cash-form aura-finance-modal__form">
                <input type="hidden" id="aura-petty-id" value="0">

                <div class="aura-modal-wizard" id="aura-petty-modal-wizard">
                    <div class="aura-modal-wizard__steps">
                        <span class="aura-modal-step is-active" data-step="1"><?php _e('1. Entrega', 'aura-suite'); ?></span>
                        <span class="aura-modal-step" data-step="2"><?php _e('2. Rendición', 'aura-suite'); ?></span>
                        <span class="aura-modal-step" data-step="3"><?php _e('3. Confirmar', 'aura-suite'); ?></span>
                    </div>

                    <section class="aura-modal-wizard__panel is-active" data-step="1">
                        <div class="aura-finance-form-grid aura-finance-form-grid--two">
                            <p>
                                <label for="aura-petty-account"><strong><?php _e('Cuenta de Caja Chica', 'aura-suite'); ?></strong></label>
                                <select id="aura-petty-account" required>
                                    <option value=""><?php _e('Selecciona una cuenta...', 'aura-suite'); ?></option>
                                </select>
                            </p>

                            <p>
                                <label for="aura-petty-responsible"><strong><?php _e('Responsable', 'aura-suite'); ?></strong></label>
                                <select id="aura-petty-responsible" required>
                                    <option value=""><?php _e('Selecciona responsable...', 'aura-suite'); ?></option>
                                    <?php foreach (get_users(array('fields' => array('ID', 'display_name'))) as $user) : ?>
                                        <option value="<?php echo esc_attr((int) $user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </p>

                            <p>
                                <label for="aura-petty-delivered"><strong><?php _e('Monto Entregado', 'aura-suite'); ?></strong></label>
                                <input type="number" id="aura-petty-delivered" min="0" step="0.01" required>
                            </p>

                            <p>
                                <label for="aura-petty-due-date"><strong><?php _e('Fecha límite de rendición', 'aura-suite'); ?></strong></label>
                                <input type="date" id="aura-petty-due-date">
                            </p>

                            <p class="aura-wizard-span-2">
                                <label for="aura-petty-notes"><strong><?php _e('Notas', 'aura-suite'); ?></strong></label>
                                <textarea id="aura-petty-notes" rows="2"></textarea>
                            </p>
                        </div>

                        <div class="aura-modal-wizard__actions">
                            <button type="button" class="button button-primary" id="aura-petty-step-next-1"><?php _e('Continuar a rendición', 'aura-suite'); ?></button>
                        </div>
                    </section>

                    <section class="aura-modal-wizard__panel" data-step="2">
                        <div class="aura-finance-form-grid aura-finance-form-grid--two">
                            <p>
                                <label for="aura-petty-spent"><strong><?php _e('Monto Gastado (al rendir)', 'aura-suite'); ?></strong></label>
                                <input type="number" id="aura-petty-spent" min="0" step="0.01" value="0">
                            </p>

                            <p>
                                <label for="aura-petty-returned"><strong><?php _e('Monto Devuelto (al rendir)', 'aura-suite'); ?></strong></label>
                                <input type="number" id="aura-petty-returned" min="0" step="0.01" value="0">
                            </p>

                            <p class="aura-wizard-span-2">
                                <label for="aura-petty-evidence"><strong><?php _e('Evidencias / Recibos', 'aura-suite'); ?></strong></label>
                                <textarea id="aura-petty-evidence" rows="2" placeholder="<?php esc_attr_e('URLs o referencia rápida de evidencias', 'aura-suite'); ?>"></textarea>
                            </p>

                            <p class="aura-wizard-span-2">
                                <label for="aura-petty-evidence-files"><strong><?php _e('Adjuntar recibos reales', 'aura-suite'); ?></strong></label>
                                <input type="file" id="aura-petty-evidence-files" multiple accept=".jpg,.jpeg,.png,.pdf,.webp">
                            </p>
                        </div>

                        <div class="aura-modal-wizard__actions">
                            <button type="button" class="button" id="aura-petty-step-back-2"><?php _e('Volver', 'aura-suite'); ?></button>
                            <button type="button" class="button button-primary" id="aura-petty-step-next-2"><?php _e('Continuar a confirmación', 'aura-suite'); ?></button>
                        </div>
                    </section>

                    <section class="aura-modal-wizard__panel" data-step="3">
                        <p class="description" id="aura-petty-step3-hint"><?php _e('Regla: Entregado = Gastado + Devuelto antes de enviar a aprobación.', 'aura-suite'); ?></p>
                        <div class="aura-petty-cash-actions">
                            <button type="button" class="button" id="aura-petty-step-back-3"><?php _e('Volver', 'aura-suite'); ?></button>
                            <button type="submit" class="button button-primary" id="aura-petty-create-btn"><?php _e('1) Registrar entrega', 'aura-suite'); ?></button>
                            <button type="button" class="button" id="aura-petty-submit-btn"><?php _e('2) Enviar rendición', 'aura-suite'); ?></button>
                            <button type="button" class="button" id="aura-petty-reset-btn"><?php _e('Limpiar', 'aura-suite'); ?></button>
                        </div>
                    </section>
                </div>
            </form>
        </div>
    </div>

    <div id="aura-finance-reimburse-modal" class="aura-finance-modal" style="display:none;" aria-hidden="true">
        <div class="aura-finance-modal__backdrop" data-modal-close="aura-finance-reimburse-modal"></div>
        <div class="aura-finance-modal__dialog aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-reimburse-modal-title">
            <div class="aura-finance-modal__head">
                <div>
                    <h2 id="aura-reimburse-modal-title" class="aura-title-with-help"><?php _e('Registrar deuda de reembolso', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Usa este modal cuando una persona pagó con su dinero y el sistema debe reconocer esa deuda.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h2>
                </div>
                <button type="button" class="button-link" data-modal-close="aura-finance-reimburse-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>

            <form id="aura-reimbursements-form" class="aura-reimburse-form-box aura-finance-modal__form">
                    <h3><?php _e('Registrar deuda', 'aura-suite'); ?></h3>
                    <p>
                        <label for="aura-reimburse-person"><strong><?php _e('Persona', 'aura-suite'); ?></strong></label>
                        <select id="aura-reimburse-person" required>
                            <option value=""><?php _e('Selecciona una persona...', 'aura-suite'); ?></option>
                            <?php foreach (get_users(array('fields' => array('ID', 'display_name'))) as $user) : ?>
                                <option value="<?php echo esc_attr((int) $user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="aura-reimburse-owed"><strong><?php _e('Valor adeudado', 'aura-suite'); ?></strong></label>
                        <input type="number" id="aura-reimburse-owed" min="0" step="0.01" required>
                    </p>
                    <p>
                        <label for="aura-reimburse-origin"><strong><?php _e('ID transacción origen (opcional)', 'aura-suite'); ?></strong></label>
                        <input type="number" id="aura-reimburse-origin" min="1" step="1" placeholder="<?php esc_attr_e('Ej: 1024', 'aura-suite'); ?>">
                    </p>
                    <p>
                        <label for="aura-reimburse-notes"><strong><?php _e('Notas', 'aura-suite'); ?></strong></label>
                        <textarea id="aura-reimburse-notes" rows="2"></textarea>
                    </p>
                    <p class="submit">
                        <button type="submit" class="button button-primary" id="aura-reimburse-create-btn"><?php _e('Registrar deuda', 'aura-suite'); ?></button>
                    </p>
            </form>
        </div>
    </div>

    <div id="aura-finance-reimburse-pay-modal" class="aura-finance-modal" style="display:none;" aria-hidden="true">
        <div class="aura-finance-modal__backdrop" data-modal-close="aura-finance-reimburse-pay-modal"></div>
        <div class="aura-finance-modal__dialog aura-finance-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="aura-reimburse-pay-modal-title">
            <div class="aura-finance-modal__head">
                <div>
                    <h2 id="aura-reimburse-pay-modal-title" class="aura-title-with-help"><?php _e('Registrar pago de reembolso', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Selecciona la cuenta que paga, define el valor y deja nota si aplica. Puedes pagar parcial o total.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h2>
                </div>
                <button type="button" class="button-link" data-modal-close="aura-finance-reimburse-pay-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">✕</button>
            </div>

            <form id="aura-reimbursements-pay-form" class="aura-reimburse-form-box aura-finance-modal__form">
                    <h3><?php _e('Registrar pago', 'aura-suite'); ?></h3>
                    <input type="hidden" id="aura-reimburse-pay-id" value="0">
                    <p>
                        <label for="aura-reimburse-pay-account"><strong><?php _e('Cuenta que paga', 'aura-suite'); ?></strong></label>
                        <select id="aura-reimburse-pay-account" required>
                            <option value=""><?php _e('Selecciona cuenta...', 'aura-suite'); ?></option>
                        </select>
                    </p>
                    <p>
                        <label for="aura-reimburse-pay-amount"><strong><?php _e('Valor a pagar', 'aura-suite'); ?></strong></label>
                        <input type="number" id="aura-reimburse-pay-amount" min="0" step="0.01" required>
                    </p>
                    <p>
                        <label for="aura-reimburse-pay-notes"><strong><?php _e('Notas del pago', 'aura-suite'); ?></strong></label>
                        <textarea id="aura-reimburse-pay-notes" rows="2"></textarea>
                    </p>
                    <p class="submit">
                        <button type="submit" class="button" id="aura-reimburse-pay-btn"><?php _e('Aplicar pago', 'aura-suite'); ?></button>
                    </p>
                    <p class="description"><?php _e('Tip: selecciona una deuda en la tabla para precargar este formulario.', 'aura-suite'); ?></p>
            </form>
        </div>
    </div>

    <div class="aura-finance-report-card">
        <div class="aura-card-head aura-card-head--reports">
            <div>
                <h2 class="aura-title-with-help"><?php _e('Reportería y Cierre', 'aura-suite'); ?><button type="button" class="aura-help-tip" data-tooltip="<?php esc_attr_e('Consolida flujo, presupuesto y auditoría. Cambia el año para analizar periodos anteriores.', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Ayuda', 'aura-suite'); ?>">?</button></h2>
                <p><?php _e('Consolida flujo por cuenta, saldos por moneda/tipo, bloque Excel, ejecución presupuestal y auditoría cruzada.', 'aura-suite'); ?></p>
            </div>
            <div class="aura-report-actions">
                <input type="number" id="aura-report-year" min="2000" max="2100" value="<?php echo esc_attr((int) current_time('Y')); ?>">
                <button type="button" class="button" id="aura-report-refresh-btn"><?php _e('Actualizar reportes', 'aura-suite'); ?></button>
            </div>
        </div>

        <div class="aura-report-kpis">
            <article class="aura-report-kpi">
                <span class="aura-report-kpi__label"><?php _e('Entradas por cuenta', 'aura-suite'); ?></span>
                <strong id="aura-report-kpi-inflows">0.00</strong>
            </article>
            <article class="aura-report-kpi">
                <span class="aura-report-kpi__label"><?php _e('Salidas por cuenta', 'aura-suite'); ?></span>
                <strong id="aura-report-kpi-outflows">0.00</strong>
            </article>
            <article class="aura-report-kpi">
                <span class="aura-report-kpi__label"><?php _e('Ejecutado anual', 'aura-suite'); ?></span>
                <strong id="aura-report-kpi-budget">0.00</strong>
            </article>
            <article class="aura-report-kpi">
                <span class="aura-report-kpi__label"><?php _e('Hallazgos auditoría', 'aura-suite'); ?></span>
                <strong id="aura-report-kpi-audit">0</strong>
            </article>
        </div>

        <div class="aura-report-grid">
            <section class="aura-report-panel">
                <h3><?php _e('Flujo por cuenta', 'aura-suite'); ?></h3>
                <div class="aura-report-table-wrap">
                    <table class="widefat striped" id="aura-report-accounts-table">
                        <thead>
                            <tr>
                                <th><?php _e('Cuenta', 'aura-suite'); ?></th>
                                <th><?php _e('Moneda', 'aura-suite'); ?></th>
                                <th><?php _e('Entradas', 'aura-suite'); ?></th>
                                <th><?php _e('Salidas', 'aura-suite'); ?></th>
                                <th><?php _e('Saldo', 'aura-suite'); ?></th>
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
                                <thead><tr><th><?php _e('Moneda', 'aura-suite'); ?></th><th><?php _e('Cuentas', 'aura-suite'); ?></th><th><?php _e('Saldo', 'aura-suite'); ?></th></tr></thead>
                                <tbody><tr><td colspan="3"><?php _e('Cargando...', 'aura-suite'); ?></td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <h4><?php _e('Tipos de cuenta', 'aura-suite'); ?></h4>
                        <div class="aura-report-table-wrap">
                            <table class="widefat striped" id="aura-report-type-table">
                                <thead><tr><th><?php _e('Tipo', 'aura-suite'); ?></th><th><?php _e('Cuentas', 'aura-suite'); ?></th><th><?php _e('Saldo', 'aura-suite'); ?></th></tr></thead>
                                <tbody><tr><td colspan="3"><?php _e('Cargando...', 'aura-suite'); ?></td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section class="aura-report-panel">
                <h3><?php _e('Bloques Excel', 'aura-suite'); ?></h3>
                <div class="aura-report-table-wrap">
                    <table class="widefat striped" id="aura-report-blocks-table">
                        <thead>
                            <tr>
                                <th><?php _e('Bloque', 'aura-suite'); ?></th>
                                <th><?php _e('Movimientos', 'aura-suite'); ?></th>
                                <th><?php _e('Ingresos', 'aura-suite'); ?></th>
                                <th><?php _e('Gastos', 'aura-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="4"><?php _e('Cargando...', 'aura-suite'); ?></td></tr></tbody>
                    </table>
                </div>
            </section>

            <section class="aura-report-panel">
                <h3><?php _e('Presupuesto: anual vs mensual', 'aura-suite'); ?></h3>
                <div class="aura-report-budget-summary" id="aura-report-budget-summary"></div>
                <div class="aura-report-table-wrap">
                    <table class="widefat striped" id="aura-report-budget-table">
                        <thead>
                            <tr>
                                <th><?php _e('Mes', 'aura-suite'); ?></th>
                                <th><?php _e('Límite', 'aura-suite'); ?></th>
                                <th><?php _e('Ejecutado', 'aura-suite'); ?></th>
                                <th><?php _e('Disponible', 'aura-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="4"><?php _e('Cargando...', 'aura-suite'); ?></td></tr></tbody>
                    </table>
                </div>
            </section>

            <section class="aura-report-panel aura-report-panel--audit">
                <h3><?php _e('Auditoría cruzada', 'aura-suite'); ?></h3>
                <div id="aura-report-audit-list" class="aura-report-audit-list"></div>
            </section>
        </div>
    </div>
</div>
