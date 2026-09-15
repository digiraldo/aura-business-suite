<?php
/**
 * Template: Configuración del Módulo Estudiantes (Fase 11)
 *
 * 3 pestañas: General | Pagos y Finanzas | Notificaciones
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Obtener configuración actual (valores guardados + defaults)
$s = Aura_Students_Settings::get_all();

// Lista de páginas WordPress para los selectores
$pages = get_pages( [ 'post_status' => 'publish', 'sort_column' => 'post_title' ] );

// Monedas comunes
$currencies = [
    'USD' => 'USD — Dólar estadounidense',
    'EUR' => 'EUR — Euro',
    'COP' => 'COP — Peso colombiano',
    'MXN' => 'MXN — Peso mexicano',
    'ARS' => 'ARS — Peso argentino',
    'PEN' => 'PEN — Sol peruano',
    'CLP' => 'CLP — Peso chileno',
    'BRL' => 'BRL — Real brasileño',
    'VES' => 'VES — Bolívar venezolano',
    'GTQ' => 'GTQ — Quetzal guatemalteco',
    'HNL' => 'HNL — Lempira hondureño',
    'BOB' => 'BOB — Boliviano',
    'PYG' => 'PYG — Guaraní paraguayo',
    'UYU' => 'UYU — Peso uruguayo',
    'CRC' => 'CRC — Colón costarricense',
    'DOP' => 'DOP — Peso dominicano',
    'NIO' => 'NIO — Córdoba nicaragüense',
];
?>
<div class="wrap aura-app-container aura-settings-wrap" id="aura-students-settings-app">

    <!-- ══════════════════ CABECERA ══════════════════ -->
    <?php
    Aura_UI::render_page_header([
        'title'    => __( 'Configuración de Estudiantes', 'aura-suite' ),
        'subtitle' => __( 'Ajustes generales, integración con finanzas y automatización de notificaciones.', 'aura-suite' ),
        'icon'     => 'dashicons-admin-generic',
        'badge'    => __( 'Fase 11', 'aura-suite' ),
    ]);
    ?>

    <!-- Aviso de éxito / error (hidden) -->
    <div id="st-settings-notice" style="display:none; margin: var(--aura-space-4, 16px) 0; padding: 12px 16px; border-radius: var(--aura-radius-md, 8px); background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; font-weight: 500;">
        <p id="st-settings-notice-msg" style="margin:0; display:flex; align-items:center; gap:8px;"></p>
    </div>

    <!-- ══════════════════ TABS ══════════════════ -->
    <div class="aura-card" style="margin-top: var(--aura-space-6, 24px); overflow: hidden; padding: 0;">

        <!-- Nav de pestañas -->
        <nav class="aura-settings-tabs-nav" role="tablist" style="display: flex; gap: 4px; padding: 8px 16px; background: var(--aura-surface-alt, #f8fafc); border-bottom: 1px solid var(--aura-border, #e2e8f0);">
            <button type="button" class="aura-tab-btn active" data-tab="general" role="tab" style="padding: 10px 18px; border: 1px solid transparent; background: transparent; border-radius: var(--aura-radius-md, 8px); cursor: pointer; font-size: 13px; font-weight: 600; color: var(--aura-text-secondary, #64748b); transition: all .15s ease; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-admin-site" style="font-size: 16px; width: 16px; height: 16px;"></span>
                <?php esc_html_e( 'General', 'aura-suite' ); ?>
            </button>
            <button type="button" class="aura-tab-btn" data-tab="finance" role="tab" style="padding: 10px 18px; border: 1px solid transparent; background: transparent; border-radius: var(--aura-radius-md, 8px); cursor: pointer; font-size: 13px; font-weight: 600; color: var(--aura-text-secondary, #64748b); transition: all .15s ease; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-money-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
                <?php esc_html_e( 'Pagos y Finanzas', 'aura-suite' ); ?>
            </button>
            <button type="button" class="aura-tab-btn" data-tab="notifications" role="tab" style="padding: 10px 18px; border: 1px solid transparent; background: transparent; border-radius: var(--aura-radius-md, 8px); cursor: pointer; font-size: 13px; font-weight: 600; color: var(--aura-text-secondary, #64748b); transition: all .15s ease; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-bell" style="font-size: 16px; width: 16px; height: 16px;"></span>
                <?php esc_html_e( 'Notificaciones', 'aura-suite' ); ?>
            </button>
        </nav>

        <div style="padding: var(--aura-space-6, 24px); max-width: 860px;">

            <!-- ══════════ TAB 1: General ══════════ -->
            <div class="aura-tab-panel active" id="tab-general" role="tabpanel">
                <div class="aura-settings-card">
                    <div style="margin-bottom: var(--aura-space-5, 20px); padding-bottom: var(--aura-space-3, 12px); border-bottom: 1px solid var(--aura-border, #e2e8f0);">
                        <h3 style="margin: 0 0 4px; font-size: var(--aura-text-lg, 1.125rem); font-weight: 600; color: var(--aura-primary-900, #4c1d95);">
                            <?php esc_html_e( 'Configuración General', 'aura-suite' ); ?>
                        </h3>
                        <p style="margin: 0; color: var(--aura-text-secondary, #64748b); font-size: 13px;">
                            <?php esc_html_e( 'Opciones y nomenclatura básica del módulo de estudiantes.', 'aura-suite' ); ?>
                        </p>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: var(--aura-space-5, 20px);">
                        <div class="aura-form-group" style="margin:0;">
                            <label for="student-code-prefix" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                <?php esc_html_e( 'Prefijo del código de estudiante', 'aura-suite' ); ?>
                            </label>
                            <input type="text" id="student-code-prefix" name="student_code_prefix"
                                   class="aura-form-control" style="max-width: 320px;" maxlength="20"
                                   value="<?php echo esc_attr( $s['student_code_prefix'] ); ?>">
                            <small style="display:block; color:var(--aura-text-muted, #94a3b8); margin-top:4px;">
                                <?php esc_html_e( 'Se usa al generar el código único del estudiante. Ej: CEM-EST → CEM-EST-001.', 'aura-suite' ); ?>
                            </small>
                        </div>

                        <div class="aura-form-group" style="margin:0;">
                            <label for="default-currency" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                <?php esc_html_e( 'Moneda por defecto', 'aura-suite' ); ?>
                            </label>
                            <select id="default-currency" name="default_currency" class="aura-form-control aura-select" style="max-width: 380px;">
                                <?php foreach ( $currencies as $code => $label ) : ?>
                                    <option value="<?php echo esc_attr( $code ); ?>"
                                        <?php selected( $s['default_currency'], $code ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display:block; color:var(--aura-text-muted, #94a3b8); margin-top:4px;">
                                <?php esc_html_e( 'Moneda usada en costos de cursos y planes de pago.', 'aura-suite' ); ?>
                            </small>
                        </div>

                        <div class="aura-form-group" style="margin:0;">
                            <label for="portal-page-id" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                <?php esc_html_e( 'Página del portal del estudiante', 'aura-suite' ); ?>
                            </label>
                            <select id="portal-page-id" name="portal_page_id" class="aura-form-control aura-select" style="max-width: 480px;">
                                <option value="0"><?php esc_html_e( '— No seleccionada —', 'aura-suite' ); ?></option>
                                <?php foreach ( $pages as $page ) : ?>
                                    <option value="<?php echo esc_attr( $page->ID ); ?>"
                                        <?php selected( $s['portal_page_id'], $page->ID ); ?>>
                                        <?php echo esc_html( $page->post_title ); ?>
                                        (ID: <?php echo esc_html( $page->ID ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display:block; color:var(--aura-text-muted, #94a3b8); margin-top:4px;">
                                <?php
                                printf(
                                    /* translators: %s shortcode name */
                                    esc_html__( 'Página que contiene el shortcode %s.', 'aura-suite' ),
                                    '<code>[aura_student_portal]</code>'
                                );
                                ?>
                            </small>
                        </div>

                        <div class="aura-form-group" style="margin:0;">
                            <label for="enrollment-page-id" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                <?php esc_html_e( 'Página de formulario de inscripción', 'aura-suite' ); ?>
                            </label>
                            <select id="enrollment-page-id" name="enrollment_page_id" class="aura-form-control aura-select" style="max-width: 480px;">
                                <option value="0"><?php esc_html_e( '— No seleccionada —', 'aura-suite' ); ?></option>
                                <?php foreach ( $pages as $page ) : ?>
                                    <option value="<?php echo esc_attr( $page->ID ); ?>"
                                        <?php selected( $s['enrollment_page_id'], $page->ID ); ?>>
                                        <?php echo esc_html( $page->post_title ); ?>
                                        (ID: <?php echo esc_html( $page->ID ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display:block; color:var(--aura-text-muted, #94a3b8); margin-top:4px;">
                                <?php esc_html_e( 'Página pública de inscripción (gestionada desde el módulo Formularios).', 'aura-suite' ); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div><!-- /#tab-general -->

            <!-- ══════════ TAB 2: Pagos y Finanzas ══════════ -->
            <div class="aura-tab-panel" id="tab-finance" role="tabpanel" style="display:none;">
                <div class="aura-settings-card">
                    <div style="margin-bottom: var(--aura-space-5, 20px); padding-bottom: var(--aura-space-3, 12px); border-bottom: 1px solid var(--aura-border, #e2e8f0);">
                        <h3 style="margin: 0 0 4px; font-size: var(--aura-text-lg, 1.125rem); font-weight: 600; color: var(--aura-primary-900, #4c1d95);">
                            <?php esc_html_e( 'Pagos y Finanzas', 'aura-suite' ); ?>
                        </h3>
                        <p style="margin: 0; color: var(--aura-text-secondary, #64748b); font-size: 13px;">
                            <?php esc_html_e( 'Control de la sincronización automática de ingresos estudiantiles con el módulo de finanzas.', 'aura-suite' ); ?>
                        </p>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: var(--aura-space-5, 20px);">
                        <div class="aura-card" style="padding: 16px; background: var(--aura-surface-alt, #f8fafc); border-radius: var(--aura-radius-lg, 12px);">
                            <label class="aura-toggle-label" style="display:flex; align-items:flex-start; gap:12px; cursor:pointer;">
                                <input type="checkbox" id="finance-integration-enabled"
                                       name="finance_integration_enabled" value="1"
                                       style="margin-top: 3px;"
                                    <?php checked( $s['finance_integration_enabled'] ); ?>>
                                <div>
                                    <strong style="color:var(--aura-text-primary, #1e293b); font-size:14px; display:block;">
                                        <?php esc_html_e( 'Registrar automáticamente pagos en el módulo Finanzas', 'aura-suite' ); ?>
                                    </strong>
                                    <span style="color:var(--aura-text-secondary, #64748b); font-size:13px; display:block; margin-top:2px;">
                                        <?php esc_html_e( 'Cuando está activo, al aprobar un pago se crea una transacción de tipo "Ingreso" en wp_aura_finance_transactions.', 'aura-suite' ); ?>
                                    </span>
                                </div>
                            </label>
                        </div>

                        <div class="aura-form-group" style="margin:0;">
                            <label for="default-currency-finance" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                <?php esc_html_e( 'Moneda por defecto', 'aura-suite' ); ?>
                            </label>
                            <select name="default_currency" class="aura-form-control aura-select" id="default-currency-finance" style="max-width: 380px;">
                                <?php foreach ( $currencies as $code => $label ) : ?>
                                    <option value="<?php echo esc_attr( $code ); ?>"
                                        <?php selected( $s['default_currency'], $code ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display:block; color:var(--aura-text-muted, #94a3b8); margin-top:4px;">
                                <?php esc_html_e( '(Mismo valor que en la pestaña General — sincronizado automáticamente.)', 'aura-suite' ); ?>
                            </small>
                        </div>

                        <div class="aura-callout" style="padding: 14px 18px; border-radius: var(--aura-radius-md, 8px); background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; font-size: 13px;">
                            <strong><?php esc_html_e( 'Nota:', 'aura-suite' ); ?></strong>
                            <?php
                            esc_html_e(
                                ' La categoría financiera a la que se asignan los pagos se configura en cada Curso (campo "Categoría financiera"). Si no se asigna categoría al curso, el pago se registra sin categoría.',
                                'aura-suite'
                            );
                            ?>
                        </div>
                    </div>
                </div>
            </div><!-- /#tab-finance -->

            <!-- ══════════ TAB 3: Notificaciones ══════════ -->
            <div class="aura-tab-panel" id="tab-notifications" role="tabpanel" style="display:none;">
                <div class="aura-settings-card">
                    <div style="margin-bottom: var(--aura-space-5, 20px); padding-bottom: var(--aura-space-3, 12px); border-bottom: 1px solid var(--aura-border, #e2e8f0);">
                        <h3 style="margin: 0 0 4px; font-size: var(--aura-text-lg, 1.125rem); font-weight: 600; color: var(--aura-primary-900, #4c1d95);">
                            <?php esc_html_e( 'Notificaciones y Alertas', 'aura-suite' ); ?>
                        </h3>
                        <p style="margin: 0; color: var(--aura-text-secondary, #64748b); font-size: 13px;">
                            <?php esc_html_e( 'Automatización de correos electrónicos para estudiantes y alertas de administración.', 'aura-suite' ); ?>
                        </p>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: var(--aura-space-5, 20px);">
                        <div class="aura-card" style="padding: 16px; background: var(--aura-surface-alt, #f8fafc); border-radius: var(--aura-radius-lg, 12px); display:flex; flex-direction:column; gap:12px;">
                            <label class="aura-toggle-label" style="display:flex; align-items:flex-start; gap:12px; cursor:pointer;">
                                <input type="checkbox" id="auto-generate-password"
                                       name="auto_generate_password" value="1"
                                       style="margin-top: 3px;"
                                    <?php checked( $s['auto_generate_password'] ); ?>>
                                <div>
                                    <strong style="color:var(--aura-text-primary, #1e293b); font-size:14px; display:block;">
                                        <?php esc_html_e( 'Generar contraseña automáticamente al aprobar la inscripción', 'aura-suite' ); ?>
                                    </strong>
                                </div>
                            </label>

                            <label class="aura-toggle-label" style="display:flex; align-items:flex-start; gap:12px; cursor:pointer;">
                                <input type="checkbox" id="send-credentials-email"
                                       name="send_credentials_email" value="1"
                                       style="margin-top: 3px;"
                                    <?php checked( $s['send_credentials_email'] ); ?>>
                                <div>
                                    <strong style="color:var(--aura-text-primary, #1e293b); font-size:14px; display:block;">
                                        <?php esc_html_e( 'Enviar correo con credenciales de acceso al portal al aprobar', 'aura-suite' ); ?>
                                    </strong>
                                </div>
                            </label>
                        </div>

                        <div class="aura-form-group" style="margin:0;">
                            <label for="reminder-days-before" style="display:block; font-size: var(--aura-text-xs, 0.75rem); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                <?php esc_html_e( 'Recordatorio de pago próximo', 'aura-suite' ); ?>
                            </label>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <input type="number" id="reminder-days-before"
                                       name="reminder_days_before" class="aura-form-control"
                                       min="1" max="30" style="max-width: 100px;"
                                       value="<?php echo esc_attr( $s['reminder_days_before'] ); ?>">
                                <span style="color:var(--aura-text-secondary, #64748b); font-size:14px;"><?php esc_html_e( 'días antes del vencimiento de la cuota.', 'aura-suite' ); ?></span>
                            </div>
                            <small style="display:block; color:var(--aura-text-muted, #94a3b8); margin-top:4px;">
                                <?php esc_html_e( 'Se enviará un recordatorio al estudiante este número de días antes del vencimiento de cada cuota.', 'aura-suite' ); ?>
                            </small>
                        </div>

                        <div class="aura-card" style="padding: 16px; background: var(--aura-surface-alt, #f8fafc); border-radius: var(--aura-radius-lg, 12px);">
                            <label class="aura-toggle-label" style="display:flex; align-items:flex-start; gap:12px; cursor:pointer;">
                                <input type="checkbox" id="overdue-alert-enabled"
                                       name="overdue_alert_enabled" value="1"
                                       style="margin-top: 3px;"
                                    <?php checked( $s['overdue_alert_enabled'] ); ?>>
                                <div>
                                    <strong style="color:var(--aura-text-primary, #1e293b); font-size:14px; display:block;">
                                        <?php esc_html_e( 'Alerta de cuota vencida', 'aura-suite' ); ?>
                                    </strong>
                                    <span style="color:var(--aura-text-secondary, #64748b); font-size:13px; display:block; margin-top:2px;">
                                        <?php esc_html_e( 'Enviar alerta al administrador cuando una cuota vence sin haber sido pagada.', 'aura-suite' ); ?>
                                    </span>
                                </div>
                            </label>
                        </div>

                        <div class="aura-callout" style="padding: 14px 18px; border-radius: var(--aura-radius-md, 8px); background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 13px;">
                            <strong><?php esc_html_e( 'Nota:', 'aura-suite' ); ?></strong>
                            <?php
                            esc_html_e(
                                ' La configuración de SMTP, WhatsApp y Google Calendar se gestiona en Configuración → Notificaciones (configuración global del plugin).',
                                'aura-suite'
                            );
                            ?>
                        </div>
                    </div>
                </div>
            </div><!-- /#tab-notifications -->

        </div>

        <!-- ══════════ BOTÓN GUARDAR ══════════ -->
        <div class="aura-settings-footer" style="padding: var(--aura-space-4, 16px) var(--aura-space-6, 24px); border-top: 1px solid var(--aura-border, #e2e8f0); background: var(--aura-surface-alt, #f8fafc); display: flex; align-items: center; gap: 12px;">
            <button type="button" id="btn-st-save-settings"
                    class="aura-btn aura-btn-primary" style="padding: 10px 24px;">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e( 'Guardar configuración', 'aura-suite' ); ?>
            </button>
            <span id="st-settings-spinner" style="display:none;">
                <div class="aura-spinner" style="width: 20px; height: 20px; border-width: 2px;"></div>
            </span>
        </div>

    </div><!-- /.aura-card -->

</div><!-- /.aura-students-settings -->

<style>
#aura-students-settings-app .aura-tab-btn.active {
    color: var(--aura-primary-700, #6d28d9) !important;
    background: var(--aura-surface, #ffffff) !important;
    border-color: var(--aura-border, #e2e8f0) !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
#aura-students-settings-app .aura-tab-btn:hover:not(.active) {
    color: var(--aura-primary-600, #7c3aed);
    background: rgba(255,255,255,0.6);
}
</style>
