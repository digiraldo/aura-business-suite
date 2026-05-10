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
<div class="wrap aura-students-wrap aura-settings-wrap" id="aura-students-settings-app">

    <!-- ══════════════════ CABECERA ══════════════════ -->
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
        <h1 style="margin:0;">⚙️ <?php esc_html_e( 'Configuración — Módulo Estudiantes', 'aura-suite' ); ?></h1>
    </div>

    <!-- Aviso de éxito / error (hidden) -->
    <div id="st-settings-notice" style="display:none;" class="notice notice-success is-dismissible">
        <p id="st-settings-notice-msg"></p>
    </div>

    <!-- ══════════════════ TABS ══════════════════ -->
    <div class="aura-settings-tabs-wrapper">

        <!-- Nav de pestañas -->
        <nav class="aura-settings-tabs-nav" role="tablist">
            <button type="button" class="aura-tab-btn active" data-tab="general" role="tab">
                🏢 <?php esc_html_e( 'General', 'aura-suite' ); ?>
            </button>
            <button type="button" class="aura-tab-btn" data-tab="finance" role="tab">
                💰 <?php esc_html_e( 'Pagos y Finanzas', 'aura-suite' ); ?>
            </button>
            <button type="button" class="aura-tab-btn" data-tab="notifications" role="tab">
                🔔 <?php esc_html_e( 'Notificaciones', 'aura-suite' ); ?>
            </button>
        </nav>

        <!-- ══════════ TAB 1: General ══════════ -->
        <div class="aura-tab-panel active" id="tab-general" role="tabpanel">
            <div class="aura-settings-card">
                <h2 class="aura-settings-card__title">
                    <?php esc_html_e( 'Configuración General', 'aura-suite' ); ?>
                </h2>
                <p class="description">
                    <?php esc_html_e( 'Opciones básicas del módulo de estudiantes.', 'aura-suite' ); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="student-code-prefix">
                                <?php esc_html_e( 'Prefijo del código de estudiante', 'aura-suite' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="student-code-prefix" name="student_code_prefix"
                                   class="regular-text" maxlength="20"
                                   value="<?php echo esc_attr( $s['student_code_prefix'] ); ?>">
                            <p class="description">
                                <?php esc_html_e( 'Se usa al generar el código único del estudiante. Ej: CEM-EST → CEM-EST-001.', 'aura-suite' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default-currency">
                                <?php esc_html_e( 'Moneda por defecto', 'aura-suite' ); ?>
                            </label>
                        </th>
                        <td>
                            <select id="default-currency" name="default_currency" class="regular-text">
                                <?php foreach ( $currencies as $code => $label ) : ?>
                                    <option value="<?php echo esc_attr( $code ); ?>"
                                        <?php selected( $s['default_currency'], $code ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Moneda usada en costos de cursos y pagos.', 'aura-suite' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="portal-page-id">
                                <?php esc_html_e( 'Página del portal del estudiante', 'aura-suite' ); ?>
                            </label>
                        </th>
                        <td>
                            <select id="portal-page-id" name="portal_page_id" class="regular-text">
                                <option value="0"><?php esc_html_e( '— No seleccionada —', 'aura-suite' ); ?></option>
                                <?php foreach ( $pages as $page ) : ?>
                                    <option value="<?php echo esc_attr( $page->ID ); ?>"
                                        <?php selected( $s['portal_page_id'], $page->ID ); ?>>
                                        <?php echo esc_html( $page->post_title ); ?>
                                        (ID: <?php echo esc_html( $page->ID ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s shortcode name */
                                    esc_html__( 'Página que contiene el shortcode %s.', 'aura-suite' ),
                                    '<code>[aura_student_portal]</code>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="enrollment-page-id">
                                <?php esc_html_e( 'Página de formulario de inscripción', 'aura-suite' ); ?>
                            </label>
                        </th>
                        <td>
                            <select id="enrollment-page-id" name="enrollment_page_id" class="regular-text">
                                <option value="0"><?php esc_html_e( '— No seleccionada —', 'aura-suite' ); ?></option>
                                <?php foreach ( $pages as $page ) : ?>
                                    <option value="<?php echo esc_attr( $page->ID ); ?>"
                                        <?php selected( $s['enrollment_page_id'], $page->ID ); ?>>
                                        <?php echo esc_html( $page->post_title ); ?>
                                        (ID: <?php echo esc_html( $page->ID ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Página pública de inscripción (gestionada desde el módulo Formularios).', 'aura-suite' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div><!-- /#tab-general -->

        <!-- ══════════ TAB 2: Pagos y Finanzas ══════════ -->
        <div class="aura-tab-panel" id="tab-finance" role="tabpanel">
            <div class="aura-settings-card">
                <h2 class="aura-settings-card__title">
                    <?php esc_html_e( 'Pagos y Finanzas', 'aura-suite' ); ?>
                </h2>
                <p class="description">
                    <?php esc_html_e( 'Control de la integración entre pagos estudiantiles y el módulo de finanzas.', 'aura-suite' ); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Integración con Módulo Finanzas', 'aura-suite' ); ?>
                        </th>
                        <td>
                            <label class="aura-toggle-label">
                                <input type="checkbox" id="finance-integration-enabled"
                                       name="finance_integration_enabled" value="1"
                                    <?php checked( $s['finance_integration_enabled'] ); ?>>
                                <?php esc_html_e( 'Registrar automáticamente cada pago estudiantil como transacción en el módulo Finanzas', 'aura-suite' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Cuando está activo, al aprobar un pago se crea una transacción de tipo "Ingreso" en wp_aura_finance_transactions.', 'aura-suite' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Moneda por defecto', 'aura-suite' ); ?>
                        </th>
                        <td>
                            <select name="default_currency" class="regular-text" id="default-currency-finance">
                                <?php foreach ( $currencies as $code => $label ) : ?>
                                    <option value="<?php echo esc_attr( $code ); ?>"
                                        <?php selected( $s['default_currency'], $code ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( '(Mismo valor que en la pestaña General — sincronizado automáticamente.)', 'aura-suite' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Nota informativa -->
                <div class="notice notice-info inline" style="margin-top:16px;">
                    <p>
                        <strong><?php esc_html_e( 'Nota:', 'aura-suite' ); ?></strong>
                        <?php
                        esc_html_e(
                            'La categoría financiera a la que se asignan los pagos se configura en cada Curso (campo "Categoría financiera"). Si no se asigna categoría al curso, el pago se registra sin categoría.',
                            'aura-suite'
                        );
                        ?>
                    </p>
                </div>
            </div>
        </div><!-- /#tab-finance -->

        <!-- ══════════ TAB 3: Notificaciones ══════════ -->
        <div class="aura-tab-panel" id="tab-notifications" role="tabpanel">
            <div class="aura-settings-card">
                <h2 class="aura-settings-card__title">
                    <?php esc_html_e( 'Notificaciones', 'aura-suite' ); ?>
                </h2>
                <p class="description">
                    <?php esc_html_e( 'Configuración de notificaciones automáticas enviadas a estudiantes y administradores.', 'aura-suite' ); ?>
                </p>

                <table class="form-table">
                    <!-- Al aprobar un estudiante -->
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Al aprobar un estudiante', 'aura-suite' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label class="aura-toggle-label" style="display:block;margin-bottom:8px;">
                                    <input type="checkbox" id="auto-generate-password"
                                           name="auto_generate_password" value="1"
                                        <?php checked( $s['auto_generate_password'] ); ?>>
                                    <?php esc_html_e( 'Generar contraseña automáticamente al aprobar la inscripción', 'aura-suite' ); ?>
                                </label>
                                <label class="aura-toggle-label" style="display:block;">
                                    <input type="checkbox" id="send-credentials-email"
                                           name="send_credentials_email" value="1"
                                        <?php checked( $s['send_credentials_email'] ); ?>>
                                    <?php esc_html_e( 'Enviar correo con credenciales de acceso al portal al aprobar', 'aura-suite' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <!-- Recordatorio de pago -->
                    <tr>
                        <th scope="row">
                            <label for="reminder-days-before">
                                <?php esc_html_e( 'Recordatorio de pago próximo', 'aura-suite' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" id="reminder-days-before"
                                   name="reminder_days_before" class="small-text"
                                   min="1" max="30"
                                   value="<?php echo esc_attr( $s['reminder_days_before'] ); ?>">
                            <span><?php esc_html_e( 'días antes del vencimiento de la cuota.', 'aura-suite' ); ?></span>
                            <p class="description">
                                <?php esc_html_e( 'Se enviará un recordatorio al estudiante este número de días antes del vencimiento de cada cuota.', 'aura-suite' ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Alerta de morosidad -->
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Alerta de cuota vencida', 'aura-suite' ); ?>
                        </th>
                        <td>
                            <label class="aura-toggle-label">
                                <input type="checkbox" id="overdue-alert-enabled"
                                       name="overdue_alert_enabled" value="1"
                                    <?php checked( $s['overdue_alert_enabled'] ); ?>>
                                <?php esc_html_e( 'Enviar alerta al administrador cuando una cuota vence sin haber sido pagada', 'aura-suite' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <!-- Nota sobre configuración SMTP -->
                <div class="notice notice-warning inline" style="margin-top:16px;">
                    <p>
                        <strong><?php esc_html_e( 'Nota:', 'aura-suite' ); ?></strong>
                        <?php
                        esc_html_e(
                            'La configuración de SMTP, WhatsApp y Google Calendar se gestiona en Configuración → Notificaciones (configuración global del plugin).',
                            'aura-suite'
                        );
                        ?>
                    </p>
                </div>
            </div>
        </div><!-- /#tab-notifications -->

        <!-- ══════════ BOTÓN GUARDAR ══════════ -->
        <div class="aura-settings-footer">
            <button type="button" id="btn-st-save-settings"
                    class="button button-primary" style="font-size:14px;padding:8px 24px;">
                <span class="dashicons dashicons-saved" style="margin-top:3px;"></span>
                <?php esc_html_e( 'Guardar configuración', 'aura-suite' ); ?>
            </button>
            <span id="st-settings-spinner" style="display:none;margin-left:10px;">
                <span class="dashicons dashicons-update-alt"
                      style="animation:spin 1s linear infinite;display:inline-block;width:20px;height:20px;font-size:20px;color:#8b5cf6;"></span>
            </span>
        </div>

    </div><!-- /.aura-settings-tabs-wrapper -->

</div><!-- /.aura-students-settings -->

<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

#aura-students-settings-app .aura-settings-tabs-wrapper {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}
#aura-students-settings-app .aura-settings-tabs-nav {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #e5e7eb;
    background: #f9fafb;
}
#aura-students-settings-app .aura-tab-btn {
    padding: 12px 20px;
    border: none;
    background: transparent;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    color: #6b7280;
    margin-bottom: -2px;
    transition: color .15s, border-color .15s;
}
#aura-students-settings-app .aura-tab-btn:hover { color: #5b21b6; }
#aura-students-settings-app .aura-tab-btn.active {
    color: #5b21b6;
    border-bottom-color: #8b5cf6;
    background: #fff;
}
#aura-students-settings-app .aura-tab-panel {
    display: none;
    padding: 24px;
}
#aura-students-settings-app .aura-tab-panel.active { display: block; }
#aura-students-settings-app .aura-settings-card {
    max-width: 800px;
}
#aura-students-settings-app .aura-settings-card__title {
    font-size: 16px;
    font-weight: 600;
    color: #5b21b6;
    margin: 0 0 8px;
    padding-bottom: 10px;
    border-bottom: 1px solid #ede9fe;
}
#aura-students-settings-app .form-table th {
    width: 250px;
    font-size: 13px;
    color: #374151;
}
#aura-students-settings-app .form-table td { font-size: 13px; }
#aura-students-settings-app .aura-toggle-label {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    cursor: pointer;
    font-size: 13px;
}
#aura-students-settings-app .aura-toggle-label input[type="checkbox"] { margin-top: 2px; }
#aura-students-settings-app .aura-settings-footer {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
    display: flex;
    align-items: center;
}
</style>

