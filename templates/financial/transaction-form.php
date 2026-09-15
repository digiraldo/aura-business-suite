<?php
/**
 * Template: Formulario de Nueva Transacción
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('aura_finance_create')) {
    wp_die(__('No tienes permisos para acceder a esta página', 'aura-suite'));
}

// Obtener categorías para el formulario
global $wpdb;
$categories_table = $wpdb->prefix . 'aura_finance_categories';
$categories = $wpdb->get_results("SELECT * FROM $categories_table WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
$accounts_table = $wpdb->prefix . 'aura_finance_accounts';
$finance_accounts = $wpdb->get_results(
    "SELECT id, name, account_type, currency, current_balance
     FROM {$accounts_table}
     WHERE deleted_at IS NULL AND is_active = 1
     ORDER BY name ASC"
);

require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-tags.php';
$available_tags = Aura_Financial_Tags::get_all_tags();

// Soporte Multi-Área y CBAC para creación de transacciones
$_frm_view_own = (
    current_user_can( 'aura_areas_view_own' )
    && ! current_user_can( 'aura_areas_view_all' )
    && ! current_user_can( 'manage_options' )
);

if ( $_frm_view_own ) {
    $_frm_areas = Aura_Areas_Setup::get_user_areas( get_current_user_id() );
    $_frm_single_area = ( count( $_frm_areas ) === 1 ) ? $_frm_areas[0] : null;
    $_frm_user_area_id = $_frm_single_area ? (int) $_frm_single_area->id : 0;
} else {
    $_frm_areas = $wpdb->get_results(
        "SELECT id, name, color FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY sort_order, name"
    );
    $_frm_single_area = null;
    $_frm_user_area_id = 0;
}
?>

<div class="wrap aura-transaction-form-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-money-alt"></span>
        <?php _e('Nueva Transacción', 'aura-suite'); ?>
    </h1>
    
    <hr class="wp-header-end">
    
    <!-- Notificaciones -->
    <div id="aura-transaction-messages" class="aura-messages"></div>

    <section class="aura-transaction-ux-head" aria-label="Estado del formulario">
        <div class="aura-transaction-ux-head__content">
            <h2><?php _e('Registrar Nueva Transacción', 'aura-suite'); ?></h2>
            <p><?php _e('Completa los datos clave y revisa la vista previa antes de guardar.', 'aura-suite'); ?></p>
        </div>
        <div class="aura-transaction-ux-head__progress">
            <div class="aura-progress-meta">
                <strong><?php _e('Progreso del formulario', 'aura-suite'); ?></strong>
                <span id="aura-tx-progress-text">0%</span>
            </div>
            <div class="aura-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Progreso de diligenciamiento">
                <span id="aura-tx-progress-bar" class="aura-progress-fill" style="width:0%;"></span>
            </div>
        </div>
    </section>

    <div id="aura-transaction-context-note" class="aura-transaction-context-note" role="status" aria-live="polite"></div>
    
    <div class="aura-transaction-container">
        <!-- Formulario Principal -->
        <div class="aura-transaction-form-main">
            <form id="aura-transaction-form" method="post" enctype="multipart/form-data">
                
                <!-- Selector de Tipo de Transacción -->
                <div class="aura-form-section aura-transaction-type-selector">
                    <div class="aura-toggle-switch aura-toggle-three">
                        <input type="radio" id="type-income" name="transaction_type" value="income" checked>
                        <label for="type-income" class="income-label">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <?php _e('Ingreso', 'aura-suite'); ?>
                        </label>

                        <input type="radio" id="type-expense" name="transaction_type" value="expense">
                        <label for="type-expense" class="expense-label">
                            <span class="dashicons dashicons-arrow-down-alt"></span>
                            <?php _e('Egreso', 'aura-suite'); ?>
                        </label>

                        <input type="radio" id="type-capital" name="transaction_type" value="capital">
                        <label for="type-capital" class="capital-label">
                            <span class="dashicons dashicons-building"></span>
                            <?php _e('Gasto de Capital', 'aura-suite'); ?>
                        </label>

                        <span class="toggle-slider"></span>
                    </div>

                    <!-- Banner explicativo — solo visible cuando se selecciona Capital -->
                    <div id="aura-capital-notice" style="display:none;margin-top:12px;background:linear-gradient(135deg,#fff8f0,#ffecd2);border-left:4px solid #e67e22;border-radius:6px;padding:12px 16px;display:none;">
                        <strong style="color:#c0392b;display:flex;align-items:center;gap:6px;">
                            <span class="dashicons dashicons-info" style="color:#e67e22;"></span>
                            <?php _e('Gasto de Capital (CapEx) — Fuera del presupuesto operacional', 'aura-suite'); ?>
                        </strong>
                        <p style="margin:6px 0 0;font-size:12px;color:#666;line-height:1.5;">
                            <?php _e('Este gasto es financiado por una <strong>donación externa o agencia</strong> para una adquisición específica. <strong>No afectará el presupuesto operacional mensual</strong> ni los porcentajes de gasto.', 'aura-suite'); ?>
                        </p>
                    </div>
                </div>
                
                <!-- Campos Principales -->
                <div class="aura-form-section">
                    <h2><?php _e('Información General', 'aura-suite'); ?></h2>
                    <p class="aura-section-help"><?php _e('Estos campos definen el impacto contable y son la base para reportes y aprobación.', 'aura-suite'); ?></p>
                    
                    <!-- Fila 1: Fecha | Categoría del gasto -->
                    <div class="aura-form-row">
                        <div class="aura-form-field aura-field-50">
                            <label for="transaction_date" class="required aura-label-with-help">
                                <span><?php _e('Fecha de Transacción', 'aura-suite'); ?></span>
                                <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('Fecha oficial en que se ejecutó el movimiento contable o bancario.', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Fecha de Transacción', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text"><span class="dashicons dashicons-calendar-alt"></span></span>
                                <input 
                                    type="text" 
                                    id="transaction_date" 
                                    name="transaction_date" 
                                    class="aura-datepicker" 
                                    placeholder="dd/mm/yyyy"
                                    value="<?php echo date('d/m/Y'); ?>"
                                    required>
                            </div>
                        </div>

                        <!-- Fase 8.4: Categoría del gasto — todas las categorías, detalle de en qué se usó el dinero -->
                        <div class="aura-form-field aura-field-50">
                            <label for="expense_category_id" class="required aura-label-with-help">
                                <span><?php _e('Categoría del gasto', 'aura-suite'); ?></span>
                                <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('Clasificación contable específica para detallar en qué se empleó el dinero.', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Categoría del gasto', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text"><span class="dashicons dashicons-tag"></span></span>
                                <select id="expense_category_id" name="expense_category_id" required>
                                    <option value=""><?php _e('Seleccionar...', 'aura-suite'); ?></option>
                                </select>
                            </div>
                            <p class="description" style="margin-top:4px;font-size:11px;color:#8c8f94;">
                                <?php _e('¿En qué se usó el dinero? (detalle del gasto)', 'aura-suite'); ?>
                            </p>
                            <div id="expense-category-desc-hint" class="aura-category-desc-box" style="display:none;margin-top:6px;padding:7px 12px;background:#f0fdf4;border-left:3px solid #10b981;border-radius:6px;font-size:12px;color:#166534;line-height:1.45;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                                <span style="font-weight:700;">ℹ️ <?php _e('Descripción:', 'aura-suite'); ?></span> <span class="desc-text" style="color:#14532d;"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Fila 2: Área / Programa | Categoría del presupuesto -->
                    <div class="aura-form-row">
                        <!-- Área / Programa -->
                        <div class="aura-form-field aura-field-50">
                            <label for="transaction_area_id" class="aura-label-with-help">
                                <span>
                                    <?php _e('Área / Programa', 'aura-suite'); ?>
                                    <?php if ( $_frm_view_own ) : ?>
                                        <small style="color:#8c8f94;font-weight:normal;"><?php _e('(asignada a tu área)', 'aura-suite'); ?></small>
                                    <?php endif; ?>
                                </span>
                                <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('Área, centro de costos o programa al que se imputa este registro contable.', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Área / Programa', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                            <?php if ( $_frm_view_own && $_frm_user_area_id ) : ?>
                                <?php
                                $ua = array_filter( $_frm_areas, fn($a) => (int) $a->id === $_frm_user_area_id );
                                $ua = reset( $ua );
                                ?>
                                <input type="hidden" name="area_id" value="<?php echo $_frm_user_area_id; ?>" data-area-name="<?php echo $ua ? esc_attr( $ua->name ) : ''; ?>">
                                <div style="display:flex;align-items:center;gap:8px;padding:6px 8px;background:#f6f7f7;border:1px solid #ddd;border-radius:4px;">
                                    <?php if ( $ua ) : ?>
                                    <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:<?php echo esc_attr( $ua->color ); ?>;flex-shrink:0;"></span>
                                    <strong><?php echo esc_html( $ua->name ); ?></strong>
                                    <?php endif; ?>
                                </div>
                            <?php else : ?>
                                <div class="aura-input-group">
                                    <span class="aura-input-group-text"><span class="dashicons dashicons-building"></span></span>
                                    <select id="transaction_area_id" name="area_id">
                                        <option value=""><?php _e('General (sin área)', 'aura-suite'); ?></option>
                                        <?php foreach ( $_frm_areas as $_fa ) : ?>
                                        <option value="<?php echo (int) $_fa->id; ?>">
                                            <?php echo esc_html( $_fa->name ); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Categoría del presupuesto — cargada dinámicamente según el área (Fase 8) -->
                        <div id="aura-budget-category-field" class="aura-form-field aura-field-50" style="display:none;">
                            <label for="category_id" class="aura-label-with-help">
                                <span>
                                    <?php _e('Categoría del presupuesto', 'aura-suite'); ?>
                                    <small style="color:#8c8f94;font-weight:normal;"><?php _e('(clasificación del gasto)', 'aura-suite'); ?></small>
                                </span>
                                <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('Rubro o concepto en qué se gasta el dinero. Aunque el área tenga presupuesto global, seleccionar la categoría permite auditar en qué se consumieron los fondos.', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Categoría del presupuesto', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text"><span class="dashicons dashicons-category"></span></span>
                                <select id="category_id" name="category_id">
                                    <option value=""><?php _e('Seleccionar categoría...', 'aura-suite'); ?></option>
                                </select>
                            </div>
                            <p class="description" style="margin-top:4px;font-size:11px;color:#8c8f94;">
                                <?php _e('¿En qué rubro se gasta? (Ej. Materiales, Mantenimiento, Servicios, etc.).', 'aura-suite'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Banner de estado del presupuesto (Fase 8 — dinámico vía JS) -->
                    <div id="aura-budget-status-banner" style="display:none;margin-bottom:12px;" role="status"></div>

                    <div class="aura-form-row">
                        <div class="aura-form-field aura-field-50">
                            <label for="amount" class="required aura-label-with-help">
                                <span><?php _e('Monto', 'aura-suite'); ?></span>
                                <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('Importe numérico de la transacción en moneda local. Mayor a 0.00.', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Monto', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                            <div class="aura-input-group aura-amount-group">
                                <span class="aura-input-group-text currency-symbol">$</span>
                                <input 
                                    type="number" 
                                    id="amount" 
                                    name="amount" 
                                    step="0.01" 
                                    min="0.01"
                                    placeholder="0.00"
                                    required>
                            </div>
                        </div>
                        
                        <div class="aura-form-field aura-field-50">
                            <label for="payment_method" class="aura-label-with-help">
                                <span><?php _e('Método de Pago', 'aura-suite'); ?></span>
                                <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('Medio utilizado para la liquidación (Efectivo, Transferencia, Cheque, Tarjeta).', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Método de Pago', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text"><span class="dashicons dashicons-money"></span></span>
                                <select id="payment_method" name="payment_method">
                                    <option value=""><?php _e('Seleccionar...', 'aura-suite'); ?></option>
                                    <option value="cash"><?php _e('Efectivo', 'aura-suite'); ?></option>
                                    <option value="transfer"><?php _e('Transferencia', 'aura-suite'); ?></option>
                                    <option value="check"><?php _e('Cheque', 'aura-suite'); ?></option>
                                    <option value="card"><?php _e('Tarjeta', 'aura-suite'); ?></option>
                                    <option value="other"><?php _e('Otro', 'aura-suite'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="aura-form-row aura-account-links">
                        <div class="aura-form-field aura-field-50 aura-account-field aura-account-source">
                            <label for="source_account_id" class="required aura-label-with-help">
                                <span><?php _e('Cuenta Origen', 'aura-suite'); ?></span>
                                <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('¿De dónde sale el dinero líquido? Si tienes efectivo o un fondo asignado a tu área, selecciona la Caja Chica de tu área. Si el pago lo realiza la administración central por banco, selecciona la cuenta bancaria.', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Cuenta Origen', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text"><span class="dashicons dashicons-money-alt"></span></span>
                                <select id="source_account_id" name="source_account_id">
                                    <option value=""><?php _e('Seleccionar cuenta origen...', 'aura-suite'); ?></option>
                                    <?php foreach ( $finance_accounts as $account ) : ?>
                                    <option
                                        value="<?php echo (int) $account->id; ?>"
                                        data-balance="<?php echo esc_attr( (string) (float) $account->current_balance ); ?>"
                                        data-currency="<?php echo esc_attr( $account->currency ); ?>">
                                        <?php echo esc_html( $account->name . ' · ' . $account->currency . ' · ' . number_format( (float) $account->current_balance, 2 ) ); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <p class="description" id="source-account-help"><?php _e('Obligatoria para egresos. Usa la Caja Chica del área si tienes el dinero en mano, o la cuenta de Banco si el pago es por transferencia institucional.', 'aura-suite'); ?></p>
                            <div class="aura-account-impact" id="source-account-impact" style="display:none;"></div>
                        </div>

                        <div class="aura-form-field aura-field-50 aura-account-field aura-account-destination">
                            <label for="destination_account_id" class="required aura-label-with-help">
                                <span><?php _e('Cuenta Destino', 'aura-suite'); ?></span>
                                <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('Cuenta bancaria o caja interna donde ingresarán los fondos (obligatoria en ingresos).', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Cuenta Destino', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text"><span class="dashicons dashicons-store"></span></span>
                                <select id="destination_account_id" name="destination_account_id">
                                    <option value=""><?php _e('Seleccionar cuenta destino...', 'aura-suite'); ?></option>
                                    <?php foreach ( $finance_accounts as $account ) : ?>
                                    <option
                                        value="<?php echo (int) $account->id; ?>"
                                        data-balance="<?php echo esc_attr( (string) (float) $account->current_balance ); ?>"
                                        data-currency="<?php echo esc_attr( $account->currency ); ?>">
                                        <?php echo esc_html( $account->name . ' · ' . $account->currency . ' · ' . number_format( (float) $account->current_balance, 2 ) ); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <p class="description" id="destination-account-help"><?php _e('Obligatoria para ingresos. Aquí entrará el dinero.', 'aura-suite'); ?></p>
                            <div class="aura-account-impact" id="destination-account-impact" style="display:none;"></div>
                        </div>
                    </div>

                    <div class="aura-form-row">
                        <div class="aura-form-field aura-field-100">
                            <label for="description" class="required aura-label-with-help">
                                <span><?php _e('Descripción', 'aura-suite'); ?></span>
                                <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('Explicación detallada del motivo de la transacción (mínimo 10 caracteres).', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Descripción', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                            <textarea 
                                id="description" 
                                name="description" 
                                rows="3"
                                minlength="10"
                                placeholder="<?php _e('Describe la transacción (mínimo 10 caracteres)...', 'aura-suite'); ?>"
                                required></textarea>
                            <span class="char-counter">0 / 10 <?php _e('caracteres mínimos', 'aura-suite'); ?></span>
                        </div>
                    </div>
                    
                    <div class="aura-form-row">
                        <div class="aura-form-field aura-field-50">
                            <label for="reference_number" class="aura-label-with-help">
                                <span><?php _e('Número de Referencia', 'aura-suite'); ?></span>
                                <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('N° de comprobante externo, factura, cheque o código de rastreo.', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Número de Referencia', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text"><span class="dashicons dashicons-tag"></span></span>
                                <input 
                                    type="text" 
                                    id="reference_number" 
                                    name="reference_number"
                                    placeholder="<?php _e('N° Factura, Cheque, etc.', 'aura-suite'); ?>">
                            </div>
                        </div>
                        
                        <div class="aura-form-field aura-field-50">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;gap:8px;flex-wrap:wrap;">
                                <label for="recipient_payer" class="aura-label-with-help" style="margin-bottom:0;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                                    <span class="label-income"><?php _e('Pagador / Empresa / Tercero', 'aura-suite'); ?></span>
                                    <span class="label-expense"><?php _e('Beneficiario / Proveedor / Tercero', 'aura-suite'); ?></span>
                                    <span class="label-capital"><?php _e('Proveedor / Tercero', 'aura-suite'); ?></span>
                                    <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('Entidad comercial, proveedor, tercero o cliente vinculado.', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Tercero / Empresa', 'aura-suite'); ?>">
                                        <span class="dashicons dashicons-editor-help"></span>
                                    </span>
                                </label>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <button type="button" class="button button-secondary aura-btn-open-tp-explorer" data-target-input="#recipient_payer" data-target-hidden="#third_party_id" style="font-size:11.5px;font-weight:600;display:inline-flex;align-items:center;gap:4px;height:26px;line-height:24px;padding:0 8px;" title="<?php esc_attr_e('Abrir catálogo y explorador clasificado por rol contable', 'aura-suite'); ?>">
                                        <span class="dashicons dashicons-groups" style="font-size:13px;width:13px;height:13px;line-height:13px;"></span> <?php _e('Seleccionar Tercero', 'aura-suite'); ?>
                                    </button>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=aura-third-parties')); ?>" target="_blank" style="font-size:11.5px;color:#64748b;text-decoration:none;font-weight:500;" title="<?php esc_attr_e('Abrir directorio de terceros y empresas', 'aura-suite'); ?>">
                                        <span class="dashicons dashicons-admin-generic" style="font-size:12px;width:12px;height:12px;line-height:12px;vertical-align:middle;"></span> <?php _e('Gestionar', 'aura-suite'); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="aura-counterparty-autocomplete-wrap" style="position:relative;">
                                <div class="aura-input-group">
                                    <span class="aura-input-group-text"><span class="dashicons dashicons-businessman"></span></span>
                                    <input 
                                        type="text" 
                                        id="recipient_payer" 
                                        name="recipient_payer"
                                        autocomplete="off"
                                        placeholder="<?php _e('Escribe o busca empresa, tienda o persona...', 'aura-suite'); ?>">
                                    <input type="hidden" id="third_party_id" name="third_party_id" value="">
                                </div>
                                
                                <div id="aura-tp-preview" class="aura-entity-preview-card" style="display:none;">
                                    <div id="aura-tp-preview-avatar" class="avatar-box"></div>
                                    <div class="entity-info">
                                        <span id="aura-tp-preview-text" class="entity-name"></span>
                                        <span id="aura-tp-preview-badge" class="aura-pill aura-pill--muted entity-badge"></span>
                                    </div>
                                    <a href="#" id="aura-tp-clear" class="entity-clear-btn" title="<?php _e('Desvincular tercero', 'aura-suite'); ?>">✕</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Usuario Vinculado al Sistema (Fase 6, Item 6.1) -->
                    <?php if (current_user_can('aura_finance_link_user') || current_user_can('manage_options')) : ?>
                    <div class="aura-form-row" id="aura-related-user-row">
                        <div class="aura-form-field aura-field-60">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;gap:8px;flex-wrap:wrap;">
                                <label for="related_user_search" style="margin-bottom:0;font-weight:600;">
                                    <?php _e('Usuario Vinculado (Sistema)', 'aura-suite'); ?>
                                </label>
                                <button type="button" class="button button-secondary aura-btn-open-tp-explorer" data-default-tab="employee" data-target-input="#related_user_search" data-target-hidden="#related_user_id" style="font-size:11.5px;font-weight:600;display:inline-flex;align-items:center;gap:4px;height:26px;line-height:24px;padding:0 8px;" title="<?php esc_attr_e('Seleccionar usuario o colaborador del sistema', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-admin-users" style="font-size:13px;width:13px;height:13px;line-height:13px;"></span> <?php _e('Seleccionar Usuario', 'aura-suite'); ?>
                                </button>
                            </div>
                            <div class="aura-user-autocomplete-wrap" style="position:relative;">
                                <div class="aura-input-group">
                                    <span class="aura-input-group-text"><span class="dashicons dashicons-admin-users"></span></span>
                                    <input type="text"
                                           id="related_user_search"
                                           placeholder="<?php _e('Buscar usuario por nombre o email...', 'aura-suite'); ?>"
                                           autocomplete="off"
                                           class="regular-text">
                                    <input type="hidden" id="related_user_id" name="related_user_id" value="">
                                </div>
                                
                                <div id="aura-user-preview" class="aura-entity-preview-card" style="display:none;">
                                    <div class="avatar-box">
                                        <img id="aura-user-avatar" src="" width="28" height="28" style="border-radius:4px;">
                                    </div>
                                    <div class="entity-info">
                                        <span id="aura-user-name" class="entity-name"></span>
                                        <span class="aura-pill aura-pill--info entity-badge"><?php _e('Usuario Sistema', 'aura-suite'); ?></span>
                                    </div>
                                    <a href="#" id="aura-user-clear" class="entity-clear-btn" title="<?php _e('Quitar usuario', 'aura-suite'); ?>">✕</a>
                                </div>
                            </div>
                        </div>
                        <div class="aura-form-field aura-field-40">
                            <label for="related_user_concept" class="aura-label-with-help" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;font-weight:600;">
                                <span><?php _e('Concepto de Vinculación', 'aura-suite'); ?></span>
                                <span class="aura-tooltip-wrap">
                                    <span class="aura-help-icon" tabindex="0" role="button" aria-label="<?php esc_attr_e('Guía de Conceptos', 'aura-suite'); ?>" style="color:#2563eb;cursor:help;display:inline-flex;align-items:center;">
                                        <span class="dashicons dashicons-editor-help" style="font-size:16px;width:16px;height:16px;"></span>
                                    </span>
                                    <span class="aura-tooltip-box" style="width:280px;white-space:normal;text-align:left;line-height:1.45;font-weight:normal;">
                                        <strong style="display:block;margin-bottom:4px;color:#93c5fd;"><?php _e('Guía de Conceptos:', 'aura-suite'); ?></strong>
                                        • <strong><?php _e('Salario/Nómina:', 'aura-suite'); ?></strong> <?php _e('Pago de sueldo. Se refleja en el Libro Mayor del empleado.', 'aura-suite'); ?><br>
                                        • <strong><?php _e('Pago a Usuario:', 'aura-suite'); ?></strong> <?php _e('Salida a terceros o proveedores.', 'aura-suite'); ?><br>
                                        • <strong><?php _e('Cobro a Usuario:', 'aura-suite'); ?></strong> <?php _e('Ingreso por cuotas o servicios.', 'aura-suite'); ?><br>
                                        • <strong><?php _e('Beca Asignada:', 'aura-suite'); ?></strong> <?php _e('Apoyo económico o subsidio.', 'aura-suite'); ?><br>
                                        • <strong><?php _e('Pago Préstamo:', 'aura-suite'); ?></strong> <?php _e('Abono o liquidación de crédito.', 'aura-suite'); ?><br>
                                        • <strong><?php _e('Reembolso:', 'aura-suite'); ?></strong> <?php _e('Devolución de dinero.', 'aura-suite'); ?><br>
                                        • <strong><?php _e('Reembolso Gastos:', 'aura-suite'); ?></strong> <?php _e('Gastos pagados por el usuario.', 'aura-suite'); ?>
                                    </span>
                                </span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text"><span class="dashicons dashicons-portfolio"></span></span>
                                <select id="related_user_concept" name="related_user_concept">
                                    <option value=""><?php _e('— Seleccionar —', 'aura-suite'); ?></option>
                                    <option value="payment_to_user"><?php _e('Pago realizado a un usuario', 'aura-suite'); ?></option>
                                    <option value="charge_to_user"><?php _e('Cobro realizado a un usuario', 'aura-suite'); ?></option>
                                    <option value="salary"><?php _e('Pago de salario/nómina', 'aura-suite'); ?></option>
                                    <option value="scholarship"><?php _e('Beca asignada', 'aura-suite'); ?></option>
                                    <option value="loan_payment"><?php _e('Pago de préstamo', 'aura-suite'); ?></option>
                                    <option value="refund"><?php _e('Reembolso', 'aura-suite'); ?></option>
                                    <option value="expense_reimbursement"><?php _e('Reembolso de gastos', 'aura-suite'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Banner Explicativo Dinámico del Concepto Seleccionado -->
                    <div id="aura-concept-info-banner" class="aura-concept-info-banner" style="display: none;">
                        <span class="dashicons dashicons-info aura-concept-banner-icon"></span>
                        <div class="aura-concept-banner-body">
                            <strong id="aura-concept-banner-title"></strong>
                            <p id="aura-concept-banner-desc"></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Campos Opcionales (colapsables) -->
                <div class="aura-form-section aura-collapsible">
                    <h2 class="aura-collapsible-header">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <?php _e('Información Adicional (Opcional)', 'aura-suite'); ?>
                    </h2>
                    
                    <div class="aura-collapsible-content" style="display: none;">
                        <div class="aura-form-row">
                            <div class="aura-form-field aura-field-100">
                                <label for="notes" class="aura-label-with-help">
                                    <span><?php _e('Notas Adicionales', 'aura-suite'); ?></span>
                                    <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('Observaciones complementarias, detalles de auditoría o acuerdos internos.', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Notas Adicionales', 'aura-suite'); ?>">
                                        <span class="dashicons dashicons-editor-help"></span>
                                    </span>
                                </label>
                                <textarea 
                                    id="notes" 
                                    name="notes" 
                                    rows="3"
                                    placeholder="<?php _e('Cualquier información adicional relevante...', 'aura-suite'); ?>"></textarea>
                            </div>
                        </div>
                        
                        <div class="aura-form-row">
                            <div class="aura-form-field aura-field-100">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;gap:8px;flex-wrap:wrap;">
                                    <label for="aura-tag-new-input" class="aura-label-with-help" style="margin-bottom:0;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                                        <span style="display:inline-flex;align-items:center;">
                                            <span class="dashicons dashicons-tag" style="font-size:16px;width:16px;height:16px;vertical-align:middle;color:#2563eb;margin-right:2px;"></span>
                                            <?php _e('Etiquetas (#tags)', 'aura-suite'); ?>
                                            <small style="color:#8c8f94;font-weight:normal;"><?php _e('(proyectos, sedes, campañas o taxonomía contable)', 'aura-suite'); ?></small>
                                        </span>
                                        <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('Etiquetas personalizadas para clasificar por proyectos, campañas o taxonomías contables.', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Etiquetas', 'aura-suite'); ?>">
                                            <span class="dashicons dashicons-editor-help"></span>
                                        </span>
                                    </label>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=aura-financial-tags')); ?>" target="_blank" style="font-size:11.5px;color:#64748b;text-decoration:none;font-weight:500;" title="<?php esc_attr_e('Abrir módulo de administración de etiquetas', 'aura-suite'); ?>">
                                        <span class="dashicons dashicons-admin-generic" style="font-size:12px;width:12px;height:12px;vertical-align:middle;"></span> <?php _e('Catálogo de Etiquetas', 'aura-suite'); ?>
                                    </a>
                                </div>

                                <!-- Componente Interactivo de Etiquetas -->
                                <div class="aura-tags-manager-box" id="aura-tags-manager-box">
                                    <!-- Contenedor de Chips Seleccionados + Input en Línea -->
                                    <div class="aura-tags-input-container" id="aura-tags-input-container">
                                        <div id="aura-selected-tags-chips" class="aura-selected-tags-chips" style="display:none;"></div>
                                        <div class="aura-tag-input-inline-wrap">
                                            <input 
                                                type="text" 
                                                id="aura-tag-new-input" 
                                                class="aura-tag-inline-field"
                                                placeholder="<?php esc_attr_e('Escribe una etiqueta y presiona Enter o coma...', 'aura-suite'); ?>"
                                                autocomplete="off">
                                            <button type="button" id="btn-add-tag-chip" class="aura-btn-add-tag" title="<?php esc_attr_e('Añadir etiqueta', 'aura-suite'); ?>">
                                                <span class="dashicons dashicons-plus"></span>
                                                <span><?php _e('Añadir', 'aura-suite'); ?></span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Input oculto que envía los tags por POST al guardar -->
                                    <input type="hidden" id="tags" name="tags" value="">

                                    <!-- Catálogo / Nube de Etiquetas Existentes para Inserción Rápida -->
                                    <?php if (!empty($available_tags)) : ?>
                                    <div class="aura-tags-suggestions-panel">
                                        <div class="aura-tags-suggestions-header">
                                            <span class="dashicons dashicons-randomize" style="font-size:13px;width:13px;height:13px;"></span>
                                            <span><?php _e('Etiquetas existentes (haz clic para seleccionar/deseleccionar):', 'aura-suite'); ?></span>
                                        </div>
                                        <div class="aura-tags-cloud-pills" id="aura-available-tags-pills">
                                            <?php foreach ($available_tags as $tag_name => $tag_count) : ?>
                                            <button type="button" class="aura-tag-pill-btn" data-tag="<?php echo esc_attr($tag_name); ?>">
                                                <span class="tag-hash">#</span><span class="tag-txt"><?php echo esc_html($tag_name); ?></span>
                                                <span class="tag-cnt"><?php echo esc_html($tag_count); ?></span>
                                            </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php else : ?>
                                    <p class="description" style="font-size:11.5px;color:#94a3b8;margin-top:6px;">
                                        <span class="dashicons dashicons-info" style="font-size:13px;width:13px;height:13px;vertical-align:middle;"></span>
                                        <?php _e('No hay etiquetas creadas todavía. Escribe cualquier nombre arriba para crear la primera.', 'aura-suite'); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="aura-form-row">
                            <div class="aura-form-field aura-field-100">
                                <label for="receipt_file" class="aura-label-with-help">
                                    <span><?php _e('Comprobante', 'aura-suite'); ?></span>
                                    <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('Archivo digital de respaldo (Factura, recibo o voucher en JPG, PNG o PDF hasta 5MB).', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Comprobante', 'aura-suite'); ?>">
                                        <span class="dashicons dashicons-editor-help"></span>
                                    </span>
                                </label>
                                <div class="aura-file-upload">
                                    <input 
                                        type="file" 
                                        id="receipt_file" 
                                        name="receipt_file"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        capture="environment">
                                    <label for="receipt_file" class="file-upload-label">
                                        <span class="dashicons dashicons-upload"></span>
                                        <span class="file-label-text"><?php _e('Subir archivo (JPG, PNG, PDF - Max 5MB)', 'aura-suite'); ?></span>
                                    </label>
                                    <div class="file-preview" style="display: none;">
                                        <div class="aura-receipt-card">
                                            <div class="aura-receipt-thumb-wrap">
                                                <img src="" alt="Comprobante" class="preview-image" style="display: none;">
                                                <div class="preview-icon-wrap" style="display: none;">
                                                    <span class="dashicons dashicons-media-document"></span>
                                                </div>
                                            </div>
                                            <div class="aura-receipt-info">
                                                <div class="aura-receipt-filename" title=""></div>
                                                <div class="aura-receipt-meta">
                                                    <span class="aura-receipt-badge aura-receipt-source"></span>
                                                    <span class="aura-receipt-type"></span>
                                                    <span class="aura-receipt-size"></span>
                                                </div>
                                            </div>
                                            <div class="aura-receipt-actions">
                                                <a href="#" target="_blank" class="button button-small aura-receipt-view-btn" title="<?php esc_attr_e('Ver comprobante en nueva pestaña', 'aura-suite'); ?>">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                    <span><?php _e('Ver', 'aura-suite'); ?></span>
                                                </a>
                                                <button type="button" class="remove-file button button-small aura-receipt-remove-btn" title="<?php esc_attr_e('Quitar comprobante', 'aura-suite'); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="receipt_file_url" name="receipt_file_url">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Acciones del Formulario -->
                <div class="aura-form-actions">
                    <button type="button" class="button" id="btn-clear-draft">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Limpiar Formulario', 'aura-suite'); ?>
                    </button>
                    
                    <div class="primary-actions">
                        <button type="button" class="button" id="btn-save-draft">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Guardar Borrador', 'aura-suite'); ?>
                        </button>
                        
                        <button type="submit" class="button button-primary" id="btn-save-transaction">
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('Guardar Transacción', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Nonce -->
                <?php wp_nonce_field('aura_transaction_nonce', 'nonce'); ?>
            </form>
        </div>
        
        <!-- Panel de Vista Previa -->
        <div class="aura-transaction-preview">
            <div class="preview-card">
                <h3><?php _e('Vista Previa', 'aura-suite'); ?></h3>
                
                <div class="preview-content">
                    <div class="preview-badge">
                        <span class="badge-type income">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <?php _e('Ingreso', 'aura-suite'); ?>
                        </span>
                    </div>

                    <div class="preview-amount">
                        <span class="amount-symbol">$</span>
                        <span class="amount-value">0.00</span>
                    </div>

                    <div class="preview-date">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span class="date-text"><?php echo date('d/m/Y'); ?></span>
                    </div>

                    <div class="preview-area" style="display:none;">
                        <span class="dashicons dashicons-building"></span>
                        <span class="area-name"></span>
                    </div>

                    <div class="preview-expense-category" style="display:none;">
                        <span class="dashicons dashicons-tag"></span>
                        <span class="exp-cat-label" style="font-size:10px;opacity:.7;display:block;"><?php _e('Categoría del gasto', 'aura-suite'); ?></span>
                        <span class="expense-category-name"></span>
                    </div>

                    <div class="preview-category" style="display:none;">
                        <span class="dashicons dashicons-category"></span>
                        <span class="pres-cat-label" style="font-size:10px;opacity:.7;display:block;"><?php _e('Presupuesto', 'aura-suite'); ?></span>
                        <span class="category-name"><?php _e('Sin categoría', 'aura-suite'); ?></span>
                    </div>

                    <div class="preview-payment" style="display:none;">
                        <span class="dashicons dashicons-money"></span>
                        <span class="payment-name"></span>
                    </div>

                    <div class="preview-recipient" style="display:none;">
                        <span class="dashicons dashicons-businessman"></span>
                        <span class="recipient-name"></span>
                    </div>

                    <div class="preview-reference" style="display:none;">
                        <span class="dashicons dashicons-id"></span>
                        <span class="reference-text"></span>
                    </div>

                    <div class="preview-description">
                        <span class="description-text"><?php _e('Sin descripción', 'aura-suite'); ?></span>
                    </div>

                    <div class="preview-tags" style="display: none;">
                        <span class="dashicons dashicons-tag"></span>
                        <span class="tags-list"></span>
                    </div>
                </div>
                
                <div class="preview-footer">
                    <small><?php _e('Los cambios se reflejan en tiempo real', 'aura-suite'); ?></small>
                </div>
            </div>
            
            <!-- Tips y Ayuda -->
            <div class="preview-tips">
                <h4>
                    <span class="dashicons dashicons-lightbulb"></span>
                    <?php _e('Consejos', 'aura-suite'); ?>
                </h4>
                <ul>
                    <li><?php _e('Asegúrate de seleccionar la categoría correcta para mejor organización', 'aura-suite'); ?></li>
                    <li><?php _e('Adjunta el comprobante para facilitar auditorías futuras', 'aura-suite'); ?></li>
                    <li><?php _e('Los <strong>Gastos de Capital</strong> no afectan el presupuesto operacional', 'aura-suite'); ?></li>
                    <li><?php _e('El formulario se guarda automáticamente cada 30 segundos', 'aura-suite'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
