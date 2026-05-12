<?php
/**
 * Vista: Configuración del Módulo de Vehículos — Fase 9 + Catálogos UX
 *
 * @package Aura_Business_Suite\Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'aura_vehicles_settings' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
}

// ── Leer opciones actuales ──────────────────────────────────────
$km_interval    = (int) get_option( 'aura_vehicles_km_before_maintenance', 5000 );
$block_rental   = (bool) get_option( 'aura_vehicles_block_with_pending_maint', false );
$alert_emails   = sanitize_textarea_field( get_option( 'aura_vehicles_alert_emails', '' ) );
$rate_per_km    = (float) get_option( 'aura_vehicles_rate_per_km', 0 );
$audit_days     = (int) get_option( 'aura_vehicles_audit_retention_days', 365 );
$module_name    = sanitize_text_field( get_option( 'aura_vehicles_module_name', '' ) );

// Fase 10 — integración financiera
$fin_enabled      = (bool) get_option( 'aura_vehicles_fin_integration_enabled', false );
$fin_income_cat   = (int) get_option( 'aura_vehicles_fin_income_category_id', 0 );
$fin_expense_cat  = (int) get_option( 'aura_vehicles_fin_expense_category_id', 0 );
$fin_sync_exp     = (bool) get_option( 'aura_vehicles_fin_sync_trip_expenses', false );
$fin_available    = class_exists( 'Aura_Vehicle_Financial_Bridge' )
                    && Aura_Vehicle_Financial_Bridge::is_financial_active();
?>
<div class="wrap aura-vehicles-settings" id="aura-veh-settings-wrap">

    <!-- ── Hero Header ─────────────────────────────────────────── -->
    <div class="aura-veh-settings-hero">
        <div class="aura-veh-settings-hero__icon">
            <span class="dashicons dashicons-admin-settings"></span>
        </div>
        <div class="aura-veh-settings-hero__text">
            <h1><?php esc_html_e( 'Configuración', 'aura-suite' ); ?></h1>
            <p><?php esc_html_e( 'Ajusta los parámetros de operación, alertas, integración y catálogos del módulo de Vehículos.', 'aura-suite' ); ?></p>
        </div>
    </div>

    <!-- Mensaje de resultado -->
    <div id="aura-veh-settings-notice" class="aura-veh-stnot" style="display:none;"></div>

    <!-- ── Layout: sidebar + contenido ────────────────────────── -->
    <div class="aura-veh-settings-layout">

        <!-- ── Sidebar de navegación ─────────────────────────── -->
        <nav class="aura-veh-settings-sidebar" role="tablist" aria-label="<?php esc_attr_e( 'Secciones de configuración', 'aura-suite' ); ?>">
            <div class="aura-veh-settings-sidebar__group">
                <div class="aura-veh-settings-sidebar__label"><?php esc_html_e( 'Configuración', 'aura-suite' ); ?></div>
                <button type="button" role="tab" class="aura-veh-settings-tab is-active" data-tab="tab-general" aria-selected="true">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'General', 'aura-suite' ); ?>
                </button>
                <button type="button" role="tab" class="aura-veh-settings-tab" data-tab="tab-operation" aria-selected="false">
                    <span class="dashicons dashicons-car"></span>
                    <?php esc_html_e( 'Operación', 'aura-suite' ); ?>
                </button>
                <button type="button" role="tab" class="aura-veh-settings-tab" data-tab="tab-audit" aria-selected="false">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Auditoría', 'aura-suite' ); ?>
                </button>
                <button type="button" role="tab" class="aura-veh-settings-tab" data-tab="tab-notif" aria-selected="false">
                    <span class="dashicons dashicons-bell"></span>
                    <?php esc_html_e( 'Notificaciones', 'aura-suite' ); ?>
                </button>
                <button type="button" role="tab" class="aura-veh-settings-tab" data-tab="tab-financial" aria-selected="false">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php esc_html_e( 'Integración Financiera', 'aura-suite' ); ?>
                </button>
            </div>
            <div class="aura-veh-settings-sidebar__group">
                <div class="aura-veh-settings-sidebar__label"><?php esc_html_e( 'Catálogos', 'aura-suite' ); ?></div>
                <button type="button" role="tab" class="aura-veh-settings-tab aura-veh-tab-catalog" data-tab="tab-destinations" data-catalog-type="destination" aria-selected="false">
                    <span class="dashicons dashicons-location"></span>
                    <?php esc_html_e( 'Destinos', 'aura-suite' ); ?>
                </button>
                <button type="button" role="tab" class="aura-veh-settings-tab aura-veh-tab-catalog" data-tab="tab-purposes" data-catalog-type="purpose" aria-selected="false">
                    <span class="dashicons dashicons-editor-help"></span>
                    <?php esc_html_e( 'Propósitos', 'aura-suite' ); ?>
                </button>
                <button type="button" role="tab" class="aura-veh-settings-tab aura-veh-tab-catalog" data-tab="tab-expenses" data-catalog-type="expense" aria-selected="false">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php esc_html_e( 'Gastos de Vehículo', 'aura-suite' ); ?>
                </button>
            </div>
        </nav><!-- /.aura-veh-settings-sidebar -->

        <!-- ── Paneles de contenido ─────────────────────────── -->
        <div class="aura-veh-settings-main">

    <div class="aura-veh-settings-body">

            <!-- ══ TAB: General ══════════════════════════════════════ -->
            <div id="tab-general" class="aura-veh-settings-panel" role="tabpanel">
                <div class="aura-veh-stpanel-header">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <div>
                        <h2><?php esc_html_e( 'Configuración General', 'aura-suite' ); ?></h2>
                        <p><?php esc_html_e( 'Identidad y datos generales del módulo de flota.', 'aura-suite' ); ?></p>
                    </div>
                </div>
                <div class="aura-veh-stcard">
                    <div class="aura-veh-strow">
                        <label class="aura-veh-strow__label" for="veh_module_name">
                            <?php esc_html_e( 'Nombre del módulo', 'aura-suite' ); ?>
                        </label>
                        <div class="aura-veh-strow__control">
                            <input type="text"
                                   id="veh_module_name"
                                   name="aura_vehicles_module_name"
                                   class="regular-text"
                                   value="<?php echo esc_attr( $module_name ); ?>"
                                   placeholder="<?php esc_attr_e( 'Gestión de Flota', 'aura-suite' ); ?>">
                            <p class="description"><?php esc_html_e( 'Nombre que aparece en encabezados y correos del módulo.', 'aura-suite' ); ?></p>
                        </div>
                    </div>
                </div>
            </div><!-- /tab-general -->

            <!-- ══ TAB: Operación ════════════════════════════════════ -->
            <div id="tab-operation" class="aura-veh-settings-panel" role="tabpanel" style="display:none;">
                <div class="aura-veh-stpanel-header">
                    <span class="dashicons dashicons-car"></span>
                    <div>
                        <h2><?php esc_html_e( 'Operación de la Flota', 'aura-suite' ); ?></h2>
                        <p><?php esc_html_e( 'Parámetros que controlan el comportamiento de las salidas y el mantenimiento.', 'aura-suite' ); ?></p>
                    </div>
                </div>
                <div class="aura-veh-stcard">
                    <div class="aura-veh-strow">
                        <label class="aura-veh-strow__label" for="veh_rate_per_km">
                            <?php esc_html_e( 'Tarifa global por km', 'aura-suite' ); ?>
                        </label>
                        <div class="aura-veh-strow__control">
                            <div class="aura-veh-input-affix">
                                <span class="aura-veh-input-affix__prefix">$</span>
                                <input type="number"
                                       id="veh_rate_per_km"
                                       name="aura_vehicles_rate_per_km"
                                       class="small-text"
                                       min="0" step="0.01"
                                       value="<?php echo esc_attr( number_format( $rate_per_km, 2, '.', '' ) ); ?>">
                                <span class="aura-veh-input-affix__suffix">/ km</span>
                            </div>
                            <p class="description"><?php esc_html_e( 'Tarifa predeterminada para salidas tipo rental (se puede sobrescribir por salida).', 'aura-suite' ); ?></p>
                        </div>
                    </div>
                    <div class="aura-veh-strow">
                        <label class="aura-veh-strow__label" for="veh_km_interval">
                            <?php esc_html_e( 'Km entre mantenimientos', 'aura-suite' ); ?>
                        </label>
                        <div class="aura-veh-strow__control">
                            <div class="aura-veh-input-affix">
                                <input type="number"
                                       id="veh_km_interval"
                                       name="aura_vehicles_km_before_maintenance"
                                       class="small-text"
                                       min="100" step="100"
                                       value="<?php echo esc_attr( $km_interval ); ?>">
                                <span class="aura-veh-input-affix__suffix">km</span>
                            </div>
                            <p class="description"><?php esc_html_e( 'Intervalo de kilometraje para generar alertas de mantenimiento. Predeterminado: 5.000 km.', 'aura-suite' ); ?></p>
                        </div>
                    </div>
                    <div class="aura-veh-strow">
                        <div class="aura-veh-strow__label">
                            <?php esc_html_e( 'Bloquear rental con mantenimiento vencido', 'aura-suite' ); ?>
                        </div>
                        <div class="aura-veh-strow__control">
                            <label class="aura-veh-toggle">
                                <input type="checkbox"
                                       id="veh_block_rental"
                                       name="aura_vehicles_block_with_pending_maint"
                                       value="1"
                                       <?php checked( $block_rental ); ?>>
                                <span class="aura-veh-toggle__track"></span>
                                <span class="aura-veh-toggle__label"><?php esc_html_e( 'No permitir registrar salidas tipo rental si el vehículo tiene mantenimiento vencido.', 'aura-suite' ); ?></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div><!-- /tab-operation -->

            <!-- ══ TAB: Auditoría ════════════════════════════════════ -->
            <div id="tab-audit" class="aura-veh-settings-panel" role="tabpanel" style="display:none;">
                <div class="aura-veh-stpanel-header">
                    <span class="dashicons dashicons-clipboard"></span>
                    <div>
                        <h2><?php esc_html_e( 'Retención de Auditoría', 'aura-suite' ); ?></h2>
                        <p><?php esc_html_e( 'Define cuánto tiempo se conservan los registros de auditoría.', 'aura-suite' ); ?></p>
                    </div>
                </div>
                <div class="aura-veh-stcard">
                    <div class="aura-veh-strow">
                        <label class="aura-veh-strow__label" for="veh_audit_days">
                            <?php esc_html_e( 'Retención de logs', 'aura-suite' ); ?>
                        </label>
                        <div class="aura-veh-strow__control">
                            <div class="aura-veh-input-affix">
                                <input type="number"
                                       id="veh_audit_days"
                                       name="aura_vehicles_audit_retention_days"
                                       class="small-text"
                                       min="30" max="3650" step="1"
                                       value="<?php echo esc_attr( $audit_days ); ?>">
                                <span class="aura-veh-input-affix__suffix"><?php esc_html_e( 'días', 'aura-suite' ); ?></span>
                            </div>
                            <p class="description"><?php esc_html_e( 'Los logs de auditoría más antiguos que este valor se podrán eliminar desde la página de Auditoría. Predeterminado: 365 días.', 'aura-suite' ); ?></p>
                        </div>
                    </div>
                </div>
                <div class="aura-veh-info-box" style="margin-top:16px;">
                    <strong><?php esc_html_e( 'Nota:', 'aura-suite' ); ?></strong>
                    <?php esc_html_e( 'La limpieza manual de logs se realiza desde la página de Auditoría usando el botón "Limpiar logs antiguos". Esta opción define el valor predeterminado que aparece en ese diálogo.', 'aura-suite' ); ?>
                </div>
            </div><!-- /tab-audit -->

            <!-- ══ TAB: Notificaciones ════════════════════════════════ -->
            <div id="tab-notif" class="aura-veh-settings-panel" role="tabpanel" style="display:none;">
                <div class="aura-veh-stpanel-header">
                    <span class="dashicons dashicons-bell"></span>
                    <div>
                        <h2><?php esc_html_e( 'Notificaciones de Mantenimiento', 'aura-suite' ); ?></h2>
                        <p><?php esc_html_e( 'Correos adicionales que recibirán alertas de mantenimiento (además de los usuarios con permiso <code>aura_vehicles_alerts</code>). Se incluirá automáticamente la información del próximo servicio estimado.', 'aura-suite' ); ?></p>
                    </div>
                </div>
                <div class="aura-veh-stcard">
                    <div class="aura-veh-strow">
                        <label class="aura-veh-strow__label" for="veh_alert_emails">
                            <?php esc_html_e( 'Correos adicionales', 'aura-suite' ); ?>
                        </label>
                        <div class="aura-veh-strow__control">
                            <textarea id="veh_alert_emails"
                                      name="aura_vehicles_alert_emails"
                                      rows="4"
                                      class="large-text"
                                      placeholder="correo1@ejemplo.com, correo2@ejemplo.com"><?php echo esc_textarea( $alert_emails ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Uno o varios correos separados por coma o salto de línea.', 'aura-suite' ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Información sobre contenido de notificaciones -->
                <div class="aura-veh-stcard" style="margin-top:16px;background:#f0f7ff;border-left:4px solid #2271b1;">
                    <h3 class="aura-veh-stcard__title" style="color:#0a4a8c;margin-bottom:8px;">
                        <span class="dashicons dashicons-info-outline" style="vertical-align:middle;margin-right:6px;"></span>
                        <?php esc_html_e( 'Información en notificaciones', 'aura-suite' ); ?>
                    </h3>
                    <p class="description" style="margin:0;">
                        <?php esc_html_e( 'Las notificaciones de mantenimiento incluyen automáticamente:', 'aura-suite' ); ?>
                    </p>
                    <ul style="margin:8px 0 0 20px;list-style:disc;">
                        <li><?php esc_html_e( 'Información del vehículo (placa, marca, modelo)', 'aura-suite' ); ?></li>
                        <li><?php esc_html_e( 'Tipo y subtipo de mantenimiento', 'aura-suite' ); ?></li>
                        <li><?php esc_html_e( 'Odómetro actual (retorno)', 'aura-suite' ); ?></li>
                        <li><?php esc_html_e( 'Próximo servicio estimado (Odómetro retorno + Intervalo km)', 'aura-suite' ); ?></li>
                        <li><?php esc_html_e( 'Costo real del mantenimiento', 'aura-suite' ); ?></li>
                        <li><?php esc_html_e( 'Responsable y área asignada', 'aura-suite' ); ?></li>
                    </ul>
                </div>

                <!-- Prueba de alerta manual -->
                <div class="aura-veh-stcard" style="margin-top:16px;">
                    <h3 class="aura-veh-stcard__title"><?php esc_html_e( 'Prueba de alerta', 'aura-suite' ); ?></h3>
                    <p class="description"><?php esc_html_e( 'Ejecuta la revisión de mantenimiento ahora mismo (equivale al cron diario).', 'aura-suite' ); ?></p>
                    <button type="button" id="aura-veh-run-alerts-now" class="button button-secondary">
                        <span class="dashicons dashicons-controls-play" style="vertical-align:middle;"></span>
                        <?php esc_html_e( 'Ejecutar revisión ahora', 'aura-suite' ); ?>
                    </button>
                    <span id="aura-veh-alerts-result" style="margin-left:12px;font-style:italic;color:#50575e;"></span>
                </div>
            </div><!-- /tab-notif -->

            <!-- ══ TAB: Integración Financiera ══════════════════════════ -->
            <div id="tab-financial" class="aura-veh-settings-panel" role="tabpanel" style="display:none;">
                <div class="aura-veh-stpanel-header">
                    <span class="dashicons dashicons-money-alt"></span>
                    <div>
                        <h2><?php esc_html_e( 'Integración con Módulo Financiero', 'aura-suite' ); ?></h2>
                        <p><?php esc_html_e( 'Sincroniza ingresos y costos de vehículos con el módulo Financial. Solo disponible si el módulo Financial está activo y configurado.', 'aura-suite' ); ?></p>
                    </div>
                </div>

                <?php if ( ! class_exists( 'Aura_Financial_Transactions' ) ) : ?>
                <div class="notice notice-warning inline" style="margin:0 0 20px;">
                    <p><?php esc_html_e( 'El módulo Financial no está disponible. Activa y configura el Módulo Financial de Aura Suite para habilitar esta integración.', 'aura-suite' ); ?></p>
                </div>
                <?php endif; ?>

                <div class="aura-veh-stcard">
                    <div class="aura-veh-strow">
                        <div class="aura-veh-strow__label"><?php esc_html_e( 'Habilitar integración', 'aura-suite' ); ?></div>
                        <div class="aura-veh-strow__control">
                            <label class="aura-veh-toggle">
                                <input type="checkbox"
                                       id="veh_fin_enabled"
                                       name="aura_vehicles_fin_integration_enabled"
                                       value="1"
                                       <?php checked( $fin_enabled ); ?>>
                                <span class="aura-veh-toggle__track"></span>
                                <span class="aura-veh-toggle__label"><?php esc_html_e( 'Crear automáticamente transacciones en el módulo Financial al cerrar salidas.', 'aura-suite' ); ?></span>
                            </label>
                        </div>
                    </div>
                    <div class="aura-veh-strow">
                        <label class="aura-veh-strow__label" for="veh_fin_income_cat">
                            <?php esc_html_e( 'Categoría de ingreso (rental)', 'aura-suite' ); ?>
                        </label>
                        <div class="aura-veh-strow__control">
                            <select id="veh_fin_income_cat"
                                    name="aura_vehicles_fin_income_category_id"
                                    class="regular-text">
                                <option value="0"><?php esc_html_e( '— Cargando... —', 'aura-suite' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Categoría que se usará al crear la transacción de ingreso cuando se cierra una salida tipo rental.', 'aura-suite' ); ?></p>
                        </div>
                    </div>
                    <div class="aura-veh-strow">
                        <label class="aura-veh-strow__label" for="veh_fin_expense_cat">
                            <?php esc_html_e( 'Categoría de egreso (gastos/mant.)', 'aura-suite' ); ?>
                        </label>
                        <div class="aura-veh-strow__control">
                            <select id="veh_fin_expense_cat"
                                    name="aura_vehicles_fin_expense_category_id"
                                    class="regular-text">
                                <option value="0"><?php esc_html_e( '— Cargando... —', 'aura-suite' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Categoría que se usará al crear la transacción de egreso cuando se cierra una salida tipo mantenimiento.', 'aura-suite' ); ?></p>
                        </div>
                    </div>
                    <div class="aura-veh-strow">
                        <div class="aura-veh-strow__label"><?php esc_html_e( 'Sincronizar gastos del trip', 'aura-suite' ); ?></div>
                        <div class="aura-veh-strow__control">
                            <label class="aura-veh-toggle">
                                <input type="checkbox"
                                       id="veh_fin_sync_expenses"
                                       name="aura_vehicles_fin_sync_trip_expenses"
                                       value="1"
                                       <?php checked( $fin_sync_exp ); ?>>
                                <span class="aura-veh-toggle__track"></span>
                                <span class="aura-veh-toggle__label"><?php esc_html_e( 'Crear una transacción de egreso por cada gasto detallado registrado en la salida (combustible, peajes, etc.). Se usará la misma categoría de egreso configurada arriba.', 'aura-suite' ); ?></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="aura-veh-info-box" style="margin-top:16px;">
                    <strong><?php esc_html_e( 'Nota:', 'aura-suite' ); ?></strong>
                    <?php esc_html_e( 'Las transacciones se crean con estado pendiente para que sigan el flujo normal de aprobación del módulo Financial. Solo se crean si la categoría seleccionada existe y está activa.', 'aura-suite' ); ?>
                </div>
            </div><!-- /tab-financial -->

            <!-- ══ TAB: Destinos ═════════════════════════════════════ -->
            <div id="tab-destinations" class="aura-veh-settings-panel aura-veh-catalog-panel" role="tabpanel" style="display:none;" data-catalog-type="destination">
                <div class="aura-veh-stpanel-header">
                    <span class="dashicons dashicons-location"></span>
                    <div>
                        <h2><?php esc_html_e( 'Destinos', 'aura-suite' ); ?></h2>
                        <p><?php esc_html_e( 'Lista de destinos disponibles al registrar una salida de tipo Encargo – Diligencias/comisión o Uso general.', 'aura-suite' ); ?></p>
                    </div>
                </div>
                <div class="aura-veh-catalog-toolbar">
                    <button type="button" class="button button-primary aura-veh-cat-add-btn" data-catalog-type="destination">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e( 'Nuevo Destino', 'aura-suite' ); ?>
                    </button>
                    <label class="aura-veh-catalog-filter">
                        <input type="checkbox" id="aura-veh-cat-show-inactive-destination">
                        <?php esc_html_e( 'Mostrar inactivos', 'aura-suite' ); ?>
                    </label>
                </div>
                <div class="aura-veh-stcard aura-veh-catalog-container" id="aura-veh-cat-list-destination">
                    <div class="aura-veh-cat-loading">
                        <span class="spinner is-active" style="float:none;vertical-align:middle;"></span>
                        <?php esc_html_e( 'Cargando destinos…', 'aura-suite' ); ?>
                    </div>
                </div>
            </div><!-- /tab-destinations -->

            <!-- ══ TAB: Propósitos ═══════════════════════════════════ -->
            <div id="tab-purposes" class="aura-veh-settings-panel aura-veh-catalog-panel" role="tabpanel" style="display:none;" data-catalog-type="purpose">
                <div class="aura-veh-stpanel-header">
                    <span class="dashicons dashicons-editor-help"></span>
                    <div>
                        <h2><?php esc_html_e( 'Propósitos', 'aura-suite' ); ?></h2>
                        <p><?php esc_html_e( 'Motivos o propósitos de viaje para salidas de tipo Encargo – Diligencias/comisión y Uso general.', 'aura-suite' ); ?></p>
                    </div>
                </div>
                <div class="aura-veh-catalog-toolbar">
                    <button type="button" class="button button-primary aura-veh-cat-add-btn" data-catalog-type="purpose">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e( 'Nuevo Propósito', 'aura-suite' ); ?>
                    </button>
                    <label class="aura-veh-catalog-filter">
                        <input type="checkbox" id="aura-veh-cat-show-inactive-purpose">
                        <?php esc_html_e( 'Mostrar inactivos', 'aura-suite' ); ?>
                    </label>
                </div>
                <div class="aura-veh-stcard aura-veh-catalog-container" id="aura-veh-cat-list-purpose">
                    <div class="aura-veh-cat-loading">
                        <span class="spinner is-active" style="float:none;vertical-align:middle;"></span>
                        <?php esc_html_e( 'Cargando propósitos…', 'aura-suite' ); ?>
                    </div>
                </div>
            </div><!-- /tab-purposes -->

            <!-- ══ TAB: Gastos de Vehículo ═══════════════════════════ -->
            <div id="tab-expenses" class="aura-veh-settings-panel aura-veh-catalog-panel" role="tabpanel" style="display:none;" data-catalog-type="expense">
                <div class="aura-veh-stpanel-header">
                    <span class="dashicons dashicons-money-alt"></span>
                    <div>
                        <h2><?php esc_html_e( 'Gastos de Vehículo', 'aura-suite' ); ?></h2>
                        <p><?php esc_html_e( 'Conceptos de gasto disponibles para registrar consumos, peajes, parqueos, mantenimiento u otros costos asociados a una salida.', 'aura-suite' ); ?></p>
                    </div>
                </div>
                <div class="aura-veh-catalog-toolbar">
                    <button type="button" class="button button-primary aura-veh-cat-add-btn" data-catalog-type="expense">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e( 'Nuevo Gasto', 'aura-suite' ); ?>
                    </button>
                    <label class="aura-veh-catalog-filter">
                        <input type="checkbox" id="aura-veh-cat-show-inactive-expense">
                        <?php esc_html_e( 'Mostrar inactivos', 'aura-suite' ); ?>
                    </label>
                </div>
                <div class="aura-veh-stcard aura-veh-catalog-container" id="aura-veh-cat-list-expense">
                    <div class="aura-veh-cat-loading">
                        <span class="spinner is-active" style="float:none;vertical-align:middle;"></span>
                        <?php esc_html_e( 'Cargando gastos…', 'aura-suite' ); ?>
                    </div>
                </div>
            </div><!-- /tab-expenses -->

            <!-- ── Barra de guardado (solo paneles de configuración) ── -->
            <div class="aura-veh-settings-save-bar" id="aura-veh-settings-save-bar">
                <button type="button" id="aura-veh-save-settings" class="button button-primary button-large">
                    <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;margin-right:4px;"></span>
                    <?php esc_html_e( 'Guardar cambios', 'aura-suite' ); ?>
                </button>
                <span class="spinner" id="aura-veh-settings-spinner" style="float:none;visibility:hidden;"></span>
                <span id="aura-veh-settings-msg" style="font-size:13px;"></span>
            </div>

        </div><!-- /.aura-veh-settings-body -->
        </div><!-- /.aura-veh-settings-main -->
    </div><!-- /.aura-veh-settings-layout -->

</div><!-- /.wrap -->

<!-- ══ Modal: Crear / Editar catálogo (desde settings) ════════════ -->
<div id="aura-veh-settings-cat-modal" class="aura-veh-modal" style="display:none;" aria-hidden="true">
    <div class="aura-veh-modal-overlay" id="aura-veh-settings-cat-overlay"></div>
    <div class="aura-veh-modal-content" role="dialog" aria-modal="true" aria-labelledby="aura-veh-settings-cat-modal-title" style="max-width:480px;">
        <div class="aura-veh-modal-header">
            <h2 id="aura-veh-settings-cat-modal-title"><?php esc_html_e( 'Nuevo ítem', 'aura-suite' ); ?></h2>
            <button type="button" id="aura-veh-settings-cat-close" class="aura-veh-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <form id="aura-veh-settings-cat-form" autocomplete="off">
            <input type="hidden" id="aura-veh-scat-type" value="">
            <input type="hidden" id="aura-veh-scat-id" value="">
            <div class="aura-veh-modal-body">
                <div class="aura-veh-form-field">
                    <label for="aura-veh-scat-name">
                        <?php esc_html_e( 'Nombre', 'aura-suite' ); ?> <span class="required">*</span>
                    </label>
                    <input type="text"
                           id="aura-veh-scat-name"
                           name="name"
                           class="regular-text"
                           maxlength="150"
                           required
                           placeholder="<?php esc_attr_e( 'Ej. Aeropuerto Internacional', 'aura-suite' ); ?>">
                </div>
                <div class="aura-veh-form-field">
                    <label for="aura-veh-scat-description">
                        <?php esc_html_e( 'Descripción', 'aura-suite' ); ?>
                        <span class="aura-veh-field-hint">(<?php esc_html_e( 'opcional', 'aura-suite' ); ?>)</span>
                    </label>
                    <input type="text"
                           id="aura-veh-scat-description"
                           name="description"
                           class="regular-text"
                           maxlength="300"
                           placeholder="<?php esc_attr_e( 'Breve aclaración o nota', 'aura-suite' ); ?>">
                </div>
                <div class="aura-veh-form-field">
                    <label><?php esc_html_e( 'Estado', 'aura-suite' ); ?></label>
                    <label class="aura-veh-toggle">
                        <input type="checkbox" id="aura-veh-scat-active" name="active" value="1" checked>
                        <span class="aura-veh-toggle__track"></span>
                        <span class="aura-veh-toggle__label"><?php esc_html_e( 'Activo', 'aura-suite' ); ?></span>
                    </label>
                </div>
                <div id="aura-veh-scat-error" class="aura-veh-scat-error" style="display:none;"></div>
            </div>
            <div class="aura-veh-modal-footer">
                <button type="submit" id="aura-veh-scat-submit" class="button button-primary">
                    <?php esc_html_e( 'Guardar', 'aura-suite' ); ?>
                </button>
                <button type="button" id="aura-veh-settings-cat-close-btn" class="button">
                    <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                </button>
                <span class="spinner" id="aura-veh-scat-spinner" style="float:none;visibility:hidden;margin-left:8px;"></span>
            </div>
        </form>
    </div>
</div><!-- /#aura-veh-settings-cat-modal -->

<!-- Datos para JS -->
<input type="hidden" id="aura-veh-cfg-nonce"       value="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
<input type="hidden" id="aura-veh-cfg-rest-base"    value="<?php echo esc_url( rest_url( 'aura/v1/' ) ); ?>">
<input type="hidden" id="aura-veh-cfg-ajax-nonce"   value="<?php echo esc_attr( wp_create_nonce( 'aura_vehicles_nonce' ) ); ?>">
<input type="hidden" id="aura-veh-cfg-ajaxurl"      value="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
<input type="hidden" id="aura-veh-cfg-fin-income"   value="<?php echo esc_attr( $fin_income_cat ); ?>">
<input type="hidden" id="aura-veh-cfg-fin-expense"  value="<?php echo esc_attr( $fin_expense_cat ); ?>">