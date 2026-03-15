<?php
/**
 * Template: Página de Configuración
 *
 * @package AuraBusinessSuite
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('aura_admin_settings')) {
    wp_die(__('No tienes permiso para acceder a esta página.', 'aura-suite'));
}

// Guardar configuración
if (isset($_POST['aura_save_settings']) && wp_verify_nonce($_POST['aura_settings_nonce'], 'save_aura_settings')) {
    
    // Configuración de notificaciones
    update_option('aura_notification_email', sanitize_email($_POST['notification_email']));
    update_option('aura_notification_from_name', sanitize_text_field($_POST['notification_from_name']));
    
    // Configuración del módulo financiero - Aprobación Automática
    update_option('aura_finance_auto_approval_enabled', isset($_POST['auto_approval_enabled']));
    update_option('aura_finance_auto_approval_threshold', floatval($_POST['auto_approval_threshold'] ?? 0));
    update_option('aura_finance_auto_approval_apply_to_expenses_only', isset($_POST['apply_to_expenses_only']));
    update_option('aura_finance_auto_approval_apply_to_income_only', isset($_POST['apply_to_income_only']));
    
    // Configuración de electricidad
    update_option('aura_electric_threshold', floatval($_POST['electric_threshold']));
    update_option('aura_electric_cost_kwh', floatval($_POST['electric_cost_kwh']));
    
    // Configuración de vehículos
    update_option('aura_vehicle_maintenance_interval', intval($_POST['vehicle_maintenance_interval']));
    update_option('aura_vehicle_alert_threshold', intval($_POST['vehicle_alert_threshold']));

    // Identidad de la organización
    $org_logo_id = absint($_POST['aura_org_logo_id'] ?? 0);
    update_option('aura_org_name',         sanitize_text_field($_POST['org_name'] ?? ''));
    update_option('aura_org_tagline',      sanitize_text_field($_POST['org_tagline'] ?? ''));
    update_option('aura_org_logo_id',      $org_logo_id);
    update_option('aura_org_logo_url',     $org_logo_id ? wp_get_attachment_image_url($org_logo_id, 'medium') : '');
    update_option('aura_org_logo_in_login',  isset($_POST['org_logo_in_login']));
    update_option('aura_org_logo_in_email',  isset($_POST['org_logo_in_email']));

    // WhatsApp global
    update_option('aura_whatsapp_enabled',       isset($_POST['whatsapp_enabled']) ? '1' : '0');
    update_option('aura_whatsapp_provider',      sanitize_key($_POST['whatsapp_provider'] ?? 'callmebot'));
    update_option('aura_whatsapp_from',          sanitize_text_field($_POST['whatsapp_from'] ?? ''));
    update_option('aura_whatsapp_twilio_sid',    sanitize_text_field($_POST['whatsapp_twilio_sid'] ?? ''));
    update_option('aura_whatsapp_meta_phone_id', sanitize_text_field($_POST['whatsapp_meta_phone_id'] ?? ''));
    update_option('aura_whatsapp_signature',     sanitize_text_field($_POST['whatsapp_signature'] ?? ''));
    // Solo actualizar token si se envió uno nuevo
    if ( ! empty( $_POST['whatsapp_api_token'] ) ) {
        update_option('aura_whatsapp_api_token', sanitize_text_field($_POST['whatsapp_api_token']));
    }

    // Google Calendar global
    update_option('aura_gcal_enabled',     isset($_POST['gcal_enabled']) ? '1' : '0');
    if ( isset($_POST['gcal_share_email']) ) {
        $gcal_emails = array_filter(array_map(function($e){ return sanitize_email(trim($e)); }, explode(',', $_POST['gcal_share_email'])));
        update_option('aura_gcal_share_email', implode(', ', $gcal_emails));
    }
    if ( isset($_POST['gcal_reminder_days']) ) {
        $gcal_days = implode(',', array_filter(array_map(function($v){
            $n = intval($v); return ($n >= 1 && $n <= 28) ? $n : null;
        }, explode(',', $_POST['gcal_reminder_days']))));
        update_option('aura_gcal_reminder_days', $gcal_days ?: '15,7,3,1');
    }
    // JSON de Service Account: solo actualizar si se envió uno nuevo
    if ( ! empty( $_POST['gcal_service_account_json'] ) ) {
        $gcal_json_raw = wp_unslash($_POST['gcal_service_account_json']);
        $gcal_parsed   = json_decode($gcal_json_raw, true);
        if ( is_array($gcal_parsed) && ($gcal_parsed['type'] ?? '') === 'service_account' ) {
            update_option('aura_gcal_service_account_json', $gcal_json_raw);
            delete_transient('aura_gcal_token');
            delete_option('aura_gcal_calendar_id_resolved');
        }
    }

    echo '<div class="notice notice-success"><p>' . __('Configuración guardada exitosamente.', 'aura-suite') . '</p></div>';
}

// Obtener configuración actual
$notification_email = get_option('aura_notification_email', get_option('admin_email'));
$notification_from_name = get_option('aura_notification_from_name', get_bloginfo('name'));
$auto_approval_enabled = get_option('aura_finance_auto_approval_enabled', false);
$auto_approval_threshold = get_option('aura_finance_auto_approval_threshold', 0);
$apply_to_expenses_only = get_option('aura_finance_auto_approval_apply_to_expenses_only', true);
$apply_to_income_only = get_option('aura_finance_auto_approval_apply_to_income_only', false);
$electric_threshold = get_option('aura_electric_threshold', 500);
$electric_cost_kwh = get_option('aura_electric_cost_kwh', 0.12);
$vehicle_maintenance_interval = get_option('aura_vehicle_maintenance_interval', 5000);
$vehicle_alert_threshold = get_option('aura_vehicle_alert_threshold', 500);

// Identidad de la organización
$org_name         = aura_get_org_name();
$org_tagline      = get_option('aura_org_tagline', '');
$org_logo_id      = (int) get_option('aura_org_logo_id', 0);
$org_logo_url     = $org_logo_id ? wp_get_attachment_image_url($org_logo_id, 'medium') : '';
$org_logo_in_login = get_option('aura_org_logo_in_login', false);
$org_logo_in_email = get_option('aura_org_logo_in_email', true);

?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-admin-settings" style="font-size: 32px; margin-right: 10px;"></span>
        <?php _e('Configuración de Aura Business Suite', 'aura-suite'); ?>
    </h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('save_aura_settings', 'aura_settings_nonce'); ?>

        <!-- Identidad de la Organización -->
        <div class="aura-config-section">
            <h2><?php _e('🏢 Identidad de la Organización', 'aura-suite'); ?></h2>
            <p class="description" style="margin-bottom:16px;">
                <?php _e('Personaliza cómo aparece tu organización en reportes exportados, emails de notificación, cabeceras del dashboard y presupuestos.', 'aura-suite'); ?>
            </p>
            <table class="form-table">

                <tr>
                    <th scope="row">
                        <label for="org_name"><?php _e('Nombre de la Organización', 'aura-suite'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="org_name" name="org_name"
                               value="<?php echo esc_attr($org_name); ?>"
                               class="regular-text"
                               placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                        <p class="description"><?php _e('Aparece en cabeceras de reportes, emails y el dashboard financiero. Si se deja vacío se usa el nombre del sitio WordPress.', 'aura-suite'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="org_tagline"><?php _e('Slogan / Descripción breve', 'aura-suite'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="org_tagline" name="org_tagline"
                               value="<?php echo esc_attr($org_tagline); ?>"
                               class="regular-text"
                               placeholder="<?php _e('Ej: Instituto de Educación Superior', 'aura-suite'); ?>">
                        <p class="description"><?php _e('Texto secundario que acompaña el nombre en reportes exportados (PDF / Excel).', 'aura-suite'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Logo de la Organización', 'aura-suite'); ?></th>
                    <td>
                        <div id="aura-org-logo-preview" style="margin-bottom:10px;min-height:40px;">
                            <?php if ($org_logo_url) : ?>
                                <img src="<?php echo esc_url($org_logo_url); ?>"
                                     style="max-height:80px;width:auto;border:1px solid #ddd;padding:6px;border-radius:4px;background:#fff;"
                                     alt="<?php echo esc_attr($org_name); ?>">
                            <?php else : ?>
                                <span id="aura-logo-no-preview" class="description">
                                    <?php _e('(sin logo configurado — se usará el logo AURA por defecto)', 'aura-suite'); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <input type="hidden" id="aura_org_logo_id" name="aura_org_logo_id"
                               value="<?php echo esc_attr($org_logo_id); ?>">

                        <button type="button" id="aura-select-logo" class="button button-secondary">
                            <span class="dashicons dashicons-format-image" style="margin-top:4px;"></span>
                            <?php echo $org_logo_id ? __('Cambiar Logo', 'aura-suite') : __('Seleccionar Logo', 'aura-suite'); ?>
                        </button>

                        <?php if ($org_logo_id) : ?>
                            <button type="button" id="aura-remove-logo" class="button" style="margin-left:8px;color:#cc1818;">
                                <span class="dashicons dashicons-trash" style="margin-top:4px;"></span>
                                <?php _e('Quitar Logo', 'aura-suite'); ?>
                            </button>
                        <?php endif; ?>

                        <p class="description" style="margin-top:8px;">
                            <?php _e('Tamaño recomendado: <strong>300&times;100&nbsp;px</strong>, formato PNG o SVG con fondo transparente.', 'aura-suite'); ?><br>
                            <?php _e('El logo se aplica en: reportes exportados, emails de notificación, dashboard financiero y presupuestos.', 'aura-suite'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Logo en página de login', 'aura-suite'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="org_logo_in_login" value="1"
                                   <?php checked($org_logo_in_login, true); ?>>
                            <?php _e('Mostrar el logo de la organización en la página de inicio de sesión de WordPress', 'aura-suite'); ?>
                        </label>
                        <p class="description"><?php _e('Solo se aplica si hay un logo seleccionado.', 'aura-suite'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Logo en emails', 'aura-suite'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="org_logo_in_email" value="1"
                                   <?php checked($org_logo_in_email, true); ?>>
                            <?php _e('Incluir el logo de la organización en la cabecera de todos los emails enviados por el sistema', 'aura-suite'); ?>
                        </label>
                        <p class="description"><?php _e('Se aplica a emails de notificaciones, alertas de inventario, aprobaciones financieras y recordatorios. Requiere que haya un logo seleccionado.', 'aura-suite'); ?></p>
                    </td>
                </tr>

            </table>

            <script>
            jQuery(document).ready(function($) {
                var mediaUploader;

                $('#aura-select-logo').on('click', function(e) {
                    e.preventDefault();
                    if (mediaUploader) { mediaUploader.open(); return; }
                    mediaUploader = wp.media({
                        title: '<?php echo esc_js(__('Seleccionar Logo de la Organización', 'aura-suite')); ?>',
                        button: { text: '<?php echo esc_js(__('Usar este logo', 'aura-suite')); ?>' },
                        multiple: false,
                        library: { type: ['image'] }
                    });
                    mediaUploader.on('select', function() {
                        var att = mediaUploader.state().get('selection').first().toJSON();
                        $('#aura_org_logo_id').val(att.id);
                        var previewUrl = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                        $('#aura-org-logo-preview').html(
                            '<img src="' + previewUrl + '" style="max-height:80px;width:auto;border:1px solid #ddd;padding:6px;border-radius:4px;background:#fff;">'
                        );
                        $('#aura-select-logo').html('<span class="dashicons dashicons-format-image" style="margin-top:4px;"></span> <?php echo esc_js(__('Cambiar Logo', 'aura-suite')); ?>');
                        if ($('#aura-remove-logo').length === 0) {
                            $('#aura-select-logo').after(
                                '<button type="button" id="aura-remove-logo" class="button" style="margin-left:8px;color:#cc1818;">'
                                + '<span class="dashicons dashicons-trash" style="margin-top:4px;"></span> <?php echo esc_js(__('Quitar Logo', 'aura-suite')); ?>'
                                + '</button>'
                            );
                            bindRemoveLogo();
                        }
                    });
                    mediaUploader.open();
                });

                function bindRemoveLogo() {
                    $(document).on('click', '#aura-remove-logo', function(e) {
                        e.preventDefault();
                        $('#aura_org_logo_id').val('');
                        $('#aura-org-logo-preview').html(
                            '<span class="description"><?php echo esc_js(__('(sin logo — se usará el logo AURA por defecto)', 'aura-suite')); ?></span>'
                        );
                        $(this).remove();
                        $('#aura-select-logo').html('<span class="dashicons dashicons-format-image" style="margin-top:4px;"></span> <?php echo esc_js(__('Seleccionar Logo', 'aura-suite')); ?>');
                    });
                }
                bindRemoveLogo();
            });
            </script>
        </div>

        <!-- Notificaciones -->
        <div class="aura-config-section">
            <h2><?php _e('📧 Configuración de Notificaciones', 'aura-suite'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="notification_email"><?php _e('Email de Notificaciones', 'aura-suite'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="notification_email" name="notification_email" 
                               value="<?php echo esc_attr($notification_email); ?>" class="regular-text" required>
                        <p class="description"><?php _e('Email desde el cual se enviarán las notificaciones del sistema.', 'aura-suite'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="notification_from_name"><?php _e('Nombre del Remitente', 'aura-suite'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="notification_from_name" name="notification_from_name" 
                               value="<?php echo esc_attr($notification_from_name); ?>" class="regular-text" required>
                        <p class="description"><?php _e('Nombre que aparecerá como remitente en los emails.', 'aura-suite'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Módulo Financiero: Aprobación Automática -->
        <div class="aura-config-section">
            <h2><?php _e('💰 Configuración del Módulo Financiero', 'aura-suite'); ?></h2>
            
            <h3 style="margin-top: 20px; color: #2271b1;"><?php _e('⚡ Aprobación Automática de Transacciones', 'aura-suite'); ?></h3>
            <p class="description" style="margin-bottom: 15px;">
                <?php _e('Configura el sistema de aprobación automática para transacciones de bajo monto. Las transacciones por debajo del umbral configurado se aprobarán automáticamente sin intervención manual.', 'aura-suite'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="auto_approval_enabled"><?php _e('Habilitar Aprobación Automática', 'aura-suite'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="auto_approval_enabled" name="auto_approval_enabled" 
                                   value="1" <?php checked($auto_approval_enabled, true); ?>>
                            <?php _e('Activar sistema de aprobación automática', 'aura-suite'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Cuando está activo, las transacciones que cumplan los criterios se aprobarán automáticamente.', 'aura-suite'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr id="threshold_row" style="<?php echo !$auto_approval_enabled ? 'display: none;' : ''; ?>">
                    <th scope="row">
                        <label for="auto_approval_threshold"><?php _e('Umbral de Monto ($)', 'aura-suite'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="auto_approval_threshold" name="auto_approval_threshold" 
                               value="<?php echo esc_attr($auto_approval_threshold); ?>"
                               step="0.01" min="0" class="regular-text" 
                               placeholder="0.00">
                        <p class="description">
                            <?php _e('Las transacciones con monto menor a este valor se aprobarán automáticamente.', 'aura-suite'); ?><br>
                            <strong><?php _e('Ejemplos:', 'aura-suite'); ?></strong>
                            <span class="aura-threshold-examples">
                                <a href="#" data-amount="100">$100</a> |
                                <a href="#" data-amount="500">$500</a> |
                                <a href="#" data-amount="1000">$1,000</a> |
                                <a href="#" data-amount="5000">$5,000</a>
                            </span>
                        </p>
                    </td>
                </tr>
                
                <tr id="application_row" style="<?php echo !$auto_approval_enabled ? 'display: none;' : ''; ?>">
                    <th scope="row">
                        <?php _e('Aplicar Auto-aprobación A:', 'aura-suite'); ?>
                    </th>
                    <td>
                        <fieldset>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="apply_to_expenses_only" value="1" 
                                       <?php checked($apply_to_expenses_only, true); ?>
                                       id="apply_to_expenses_only">
                                <?php _e('Solo Egresos', 'aura-suite'); ?>
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="apply_to_income_only" value="1" 
                                       <?php checked($apply_to_income_only, true); ?>
                                       id="apply_to_income_only">
                                <?php _e('Solo Ingresos', 'aura-suite'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Si no seleccionas ninguna, se aplicará a ambos tipos de transacciones.', 'aura-suite'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr id="stats_row" style="<?php echo !$auto_approval_enabled ? 'display: none;' : ''; ?>">
                    <th scope="row">
                        <?php _e('Estadísticas Actuales', 'aura-suite'); ?>
                    </th>
                    <td>
                        <?php
                        if ($auto_approval_enabled && $auto_approval_threshold > 0) {
                            $stats = Aura_Financial_Settings::get_auto_approval_stats('month');
                            ?>
                            <div class="aura-approval-stats-box" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 15px; max-width: 400px;">
                                <h4 style="margin: 0 0 10px 0; color: #374151;"><?php _e('Este Mes:', 'aura-suite'); ?></h4>
                                <ul style="margin: 0; padding-left: 20px; color: #6b7280;">
                                    <li><?php printf(__('Total transacciones: <strong>%d</strong>', 'aura-suite'), $stats['total']); ?></li>
                                    <li style="color: #10b981;"><?php printf(__('Auto-aprobadas: <strong>%d (%s%%)</strong>', 'aura-suite'), $stats['auto_approved'], $stats['auto_approved_percent']); ?></li>
                                    <li style="color: #3b82f6;"><?php printf(__('Aprobación manual: <strong>%d (%s%%)</strong>', 'aura-suite'), $stats['manual_approved'], $stats['manual_approved_percent']); ?></li>
                                    <li style="color: #ef4444;"><?php printf(__('Rechazadas: <strong>%d (%s%%)</strong>', 'aura-suite'), $stats['rejected'], $stats['rejected_percent']); ?></li>
                                </ul>
                                <?php if ($stats['time_saved_hours'] > 0): ?>
                                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e5e7eb;">
                                        <span style="color: #10b981; font-weight: bold; font-size: 18px;">⏱️ <?php echo $stats['time_saved_hours']; ?> hrs</span>
                                        <br>
                                        <small style="color: #6b7280;"><?php _e('Tiempo ahorrado este mes', 'aura-suite'); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php } else { ?>
                            <p class="description">
                                <?php _e('Las estadísticas se mostrarán una vez que habilites la aprobación automática y configures un umbral.', 'aura-suite'); ?>
                            </p>
                        <?php } ?>
                    </td>
                </tr>
            </table>
            
            <h3 style="margin-top: 30px; color: #2271b1;"><?php _e('🚫 Excepciones de Aprobación Automática', 'aura-suite'); ?></h3>
            <p class="description" style="margin-bottom: 15px;">
                <?php _e('Configura categorías y módulos que siempre requieran aprobación manual, independientemente del monto.', 'aura-suite'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <?php _e('Categorías con Aprobación Forzada', 'aura-suite'); ?>
                    </th>
                    <td>
                        <?php
                        global $wpdb;
                        $table = $wpdb->prefix . 'aura_finance_categories';
                        $categories = $wpdb->get_results(
                            "SELECT id, name, type, always_require_approval 
                             FROM $table 
                             WHERE is_active = 1 
                             ORDER BY type, name"
                        );
                        
                        if ($categories) {
                            echo '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fafafa;">';
                            $current_type = '';
                            foreach ($categories as $cat) {
                                if ($current_type !== $cat->type) {
                                    if ($current_type !== '') echo '</div>';
                                    $type_label = $cat->type === 'income' ? __('Ingresos', 'aura-suite') : __('Egresos', 'aura-suite');
                                    echo '<h4 style="margin: 10px 0 5px 0; color: #2563eb;">' . $type_label . '</h4>';
                                    echo '<div style="margin-left: 10px;">';
                                    $current_type = $cat->type;
                                }
                                
                                $checked = !empty($cat->always_require_approval) ? 'checked' : '';
                                printf(
                                    '<label style="display: block; margin: 5px 0;">' .
                                    '<input type="checkbox" class="category-exception" data-category-id="%d" %s> ' .
                                    '%s' .
                                    '</label>',
                                    $cat->id,
                                    $checked,
                                    esc_html($cat->name)
                                );
                            }
                            echo '</div></div>';
                        }
                        ?>
                        <p class="description">
                            <?php _e('Las categorías marcadas siempre requerirán aprobación manual, sin importar el monto.', 'aura-suite'); ?><br>
                            <strong><?php _e('Ejemplo:', 'aura-suite'); ?></strong> <?php _e('Puedes marcar "Nómina" o "Becas" para que siempre requieran supervisión.', 'aura-suite'); ?>
                        </p>
                        <button type="button" id="save-category-exceptions" class="button button-secondary" style="margin-top: 10px;">
                            <?php _e('Guardar Excepciones de Categorías', 'aura-suite'); ?>
                        </button>
                        <span id="category-save-status" style="margin-left: 10px;"></span>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <?php _e('Información', 'aura-suite'); ?>
                    </th>
                    <td>
                        <div style="background: #e0f2fe; border-left: 4px solid #0284c7; padding: 12px; border-radius: 4px;">
                            <h4 style="margin: 0 0 8px 0; color: #0c4a6e;"><?php _e('ℹ️ Casos de Uso Típicos:', 'aura-suite'); ?></h4>
                            <ul style="margin: 5px 0 5px 20px; color: #0c4a6e;">
                                <li><?php _e('<strong>Empresa de Servicios:</strong> Umbral $500 - Suministros se auto-aprueban, equipo IT requiere aprobación', 'aura-suite'); ?></li>
                                <li><?php _e('<strong>Fundación:</strong> Umbral $200 solo egresos - Todos los ingresos requieren aprobación para transparencia', 'aura-suite'); ?></li>
                                <li><?php _e('<strong>Instituto Educativo:</strong> Umbral $1,000 - Mantenimiento menor se auto-aprueba, nómina siempre requiere aprobación', 'aura-suite'); ?></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Electricidad -->
        <div class="aura-config-section">
            <h2><?php _e('⚡ Configuración de Electricidad', 'aura-suite'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="electric_threshold"><?php _e('Umbral de Alerta (kWh/día)', 'aura-suite'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="electric_threshold" name="electric_threshold" 
                               value="<?php echo esc_attr($electric_threshold); ?>" step="0.01" min="0" class="regular-text" required>
                        <p class="description"><?php _e('Se enviará una alerta cuando el consumo diario supere este valor.', 'aura-suite'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="electric_cost_kwh"><?php _e('Costo por kWh ($)', 'aura-suite'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="electric_cost_kwh" name="electric_cost_kwh" 
                               value="<?php echo esc_attr($electric_cost_kwh); ?>" step="0.01" min="0" class="regular-text" required>
                        <p class="description"><?php _e('Costo por defecto de cada kWh para calcular gastos proyectados.', 'aura-suite'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Vehículos -->
        <div class="aura-config-section">
            <h2><?php _e('🚗 Configuración de Vehículos', 'aura-suite'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="vehicle_maintenance_interval"><?php _e('Intervalo de Mantenimiento (km)', 'aura-suite'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="vehicle_maintenance_interval" name="vehicle_maintenance_interval" 
                               value="<?php echo esc_attr($vehicle_maintenance_interval); ?>" min="0" class="regular-text" required>
                        <p class="description"><?php _e('Kilometraje por defecto entre mantenimientos.', 'aura-suite'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="vehicle_alert_threshold"><?php _e('Umbral de Alerta (km)', 'aura-suite'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="vehicle_alert_threshold" name="vehicle_alert_threshold" 
                               value="<?php echo esc_attr($vehicle_alert_threshold); ?>" min="0" class="regular-text" required>
                        <p class="description"><?php _e('Se enviará alerta cuando falten menos de estos kilómetros para el mantenimiento.', 'aura-suite'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- API de Electricidad -->
        <div class="aura-config-section">
            <h2><?php _e('🔌 API de Electricidad para IoT', 'aura-suite'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('API Key', 'aura-suite'); ?></th>
                    <td>
                        <?php
                        $api_key = get_option('aura_electricity_api_key', '');
                        if (empty($api_key)) {
                            $api_key = wp_generate_password(32, false);
                            update_option('aura_electricity_api_key', $api_key);
                        }
                        ?>
                        <code style="background: #f9fafb; padding: 8px 12px; border-radius: 4px; display: inline-block; font-size: 14px;">
                            <?php echo esc_html($api_key); ?>
                        </code>
                        <p class="description">
                            <?php _e('Usa esta clave para enviar lecturas desde dispositivos IoT.', 'aura-suite'); ?><br>
                            <strong>Endpoint POST:</strong> <code><?php echo rest_url('aura/v1/electricity/reading'); ?></code><br>
                            <strong>Ejemplo:</strong> <code>{"reading_kwh": 450.5, "api_key": "<?php echo esc_html(substr($api_key, 0, 16)); ?>..."}</code>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Categorías Financieras -->
        <div class="aura-config-section">
            <h2><?php _e('💼 Categorías Financieras', 'aura-suite'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Categorías Instaladas', 'aura-suite'); ?></th>
                    <td>
                        <?php
                        $cat_version = get_option('aura_finance_categories_installed', 'No instaladas');
                        $setup = new Aura_Financial_Setup();
                        $stats = $setup->get_categories_stats();
                        ?>
                        <p>
                            <strong><?php _e('Versión:', 'aura-suite'); ?></strong> <?php echo esc_html($cat_version); ?><br>
                            <strong><?php _e('Total de categorías:', 'aura-suite'); ?></strong> <?php echo esc_html($stats['total']); ?><br>
                            <strong><?php _e('Categorías de ingreso:', 'aura-suite'); ?></strong> <?php echo esc_html($stats['income']); ?><br>
                            <strong><?php _e('Categorías de gasto:', 'aura-suite'); ?></strong> <?php echo esc_html($stats['expense']); ?><br>
                            <strong><?php _e('Categorías principales:', 'aura-suite'); ?></strong> <?php echo esc_html($stats['main_categories']); ?><br>
                            <strong><?php _e('Subcategorías:', 'aura-suite'); ?></strong> <?php echo esc_html($stats['subcategories']); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Reinstalar Categorías', 'aura-suite'); ?></th>
                    <td>
                        <button type="button" id="aura-reinstall-categories" class="button button-secondary">
                            <span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
                            <?php _e('Reinstalar Categorías Predeterminadas', 'aura-suite'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Esta acción volverá a instalar todas las categorías predeterminadas. Las categorías personalizadas no se eliminarán.', 'aura-suite'); ?>
                        </p>
                        <div id="reinstall-result" style="margin-top: 10px;"></div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ═══════════════════════════════════════════════════════
             WHATSAPP GLOBAL
             ═══════════════════════════════════════════════════════ -->
        <div class="aura-config-section">
            <h2>
                <span class="dashicons dashicons-smartphone" style="color:#25d366;vertical-align:middle;margin-right:6px;font-size:22px;"></span>
                <?php _e('📱 WhatsApp — Configuración Global', 'aura-suite'); ?>
            </h2>
            <p class="description" style="margin-bottom:16px;">
                <?php _e('Configura el proveedor de WhatsApp Business para enviar notificaciones a usuarios externos desde cualquier módulo (Inventario, etc.). La configuración se aplica a todo el sistema.', 'aura-suite'); ?>
            </p>

            <div id="js-global-wa-msg" style="display:none;margin-bottom:12px;"></div>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Activar WhatsApp', 'aura-suite'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="whatsapp_enabled" value="1"
                                   <?php checked(get_option('aura_whatsapp_enabled', '0'), '1'); ?>>
                            <?php _e('Enviar notificaciones WhatsApp desde el sistema', 'aura-suite'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gset-wa-provider"><?php _e('Proveedor', 'aura-suite'); ?></label></th>
                    <td>
                        <select name="whatsapp_provider" id="gset-wa-provider" class="regular-text">
                            <option value="callmebot" <?php selected(get_option('aura_whatsapp_provider','callmebot'),'callmebot'); ?>>CallMeBot (gratuito)</option>
                            <option value="twilio"    <?php selected(get_option('aura_whatsapp_provider','callmebot'),'twilio');    ?>>Twilio</option>
                            <option value="meta"      <?php selected(get_option('aura_whatsapp_provider','callmebot'),'meta');      ?>>Meta / WhatsApp Cloud API</option>
                        </select>
                        <p class="description gwa-desc-callmebot">
                            <?php printf(__('CallMeBot es gratuito. El destinatario debe aceptarlo primero. <a href="%s" target="_blank" rel="noopener">Ver instrucciones</a>.','aura-suite'),'https://www.callmebot.com/blog/free-api-whatsapp-messages/'); ?>
                        </p>
                        <p class="description gwa-desc-twilio" style="display:none;"><?php _e('Requiere cuenta Twilio con número habilitado para WhatsApp Business.','aura-suite'); ?></p>
                        <p class="description gwa-desc-meta"   style="display:none;"><?php _e('Requiere app en Meta for Developers con permiso whatsapp_business_messaging.','aura-suite'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gset-wa-token"><?php _e('API Token', 'aura-suite'); ?></label></th>
                    <td>
                        <input type="password" name="whatsapp_api_token" id="gset-wa-token" class="regular-text"
                               autocomplete="new-password"
                               value="<?php echo esc_attr(get_option('aura_whatsapp_api_token','')); ?>"
                               placeholder="<?php esc_attr_e('Token API (CallMeBot / Twilio Auth Token / Meta Bearer)','aura-suite'); ?>">
                        <p class="description"><?php _e('Déjalo en blanco para conservar el token guardado.','aura-suite'); ?></p>
                    </td>
                </tr>
                <tr class="gwa-row-from">
                    <th scope="row"><label for="gset-wa-from"><?php _e('Número origen', 'aura-suite'); ?></label></th>
                    <td>
                        <input type="text" name="whatsapp_from" id="gset-wa-from" class="regular-text"
                               value="<?php echo esc_attr(get_option('aura_whatsapp_from','')); ?>"
                               placeholder="+521234567890">
                        <p class="description gwa-desc-from-callmebot"><?php _e('No requerido para CallMeBot.','aura-suite'); ?></p>
                        <p class="description gwa-desc-from-twilio" style="display:none;"><?php _e('Número Twilio habilitado para WhatsApp, ej. +14155238886','aura-suite'); ?></p>
                        <p class="description gwa-desc-from-meta"   style="display:none;"><?php _e('No requerido aquí para Meta; usa el campo Phone ID.','aura-suite'); ?></p>
                    </td>
                </tr>
                <tr class="gwa-row-twilio" style="display:none;">
                    <th scope="row"><label for="gset-wa-twilio-sid"><?php _e('Twilio Account SID', 'aura-suite'); ?></label></th>
                    <td>
                        <input type="text" name="whatsapp_twilio_sid" id="gset-wa-twilio-sid" class="regular-text"
                               value="<?php echo esc_attr(get_option('aura_whatsapp_twilio_sid','')); ?>"
                               placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    </td>
                </tr>
                <tr class="gwa-row-meta" style="display:none;">
                    <th scope="row"><label for="gset-wa-meta-phone"><?php _e('Meta Phone Number ID', 'aura-suite'); ?></label></th>
                    <td>
                        <input type="text" name="whatsapp_meta_phone_id" id="gset-wa-meta-phone" class="regular-text"
                               value="<?php echo esc_attr(get_option('aura_whatsapp_meta_phone_id','')); ?>"
                               placeholder="123456789012345">
                        <p class="description"><?php _e('ID del número en el panel de Meta for Developers.','aura-suite'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gset-wa-signature"><?php _e('Firma del mensaje', 'aura-suite'); ?></label></th>
                    <td>
                        <input type="text" name="whatsapp_signature" id="gset-wa-signature" class="regular-text"
                               value="<?php echo esc_attr(get_option('aura_whatsapp_signature', aura_get_org_name())); ?>"
                               placeholder="<?php echo esc_attr(aura_get_org_name()); ?>">
                        <p class="description"><?php _e('Texto al final de cada mensaje, ej. nombre de tu organización.','aura-suite'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Prueba de WhatsApp', 'aura-suite'); ?></th>
                    <td>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <input type="text" id="gset-wa-test-phone" class="regular-text"
                                   placeholder="+521234567890" style="max-width:200px;">
                            <button type="button" class="button button-secondary" id="js-btn-global-wa-test"
                                    style="background:#e8f8f0;border-color:#25d366;color:#1a7c45;">
                                <span class="dashicons dashicons-smartphone"></span>
                                <?php _e('Enviar prueba WhatsApp','aura-suite'); ?>
                            </button>
                        </div>
                        <p class="description"><?php _e('Guarda la configuración primero, luego ingresa un número y envía el mensaje de prueba.','aura-suite'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ═══════════════════════════════════════════════════════
             GOOGLE CALENDAR GLOBAL
             ═══════════════════════════════════════════════════════ -->
        <div class="aura-config-section">
            <h2>
                <span class="dashicons dashicons-calendar-alt" style="color:#4285f4;vertical-align:middle;margin-right:6px;font-size:22px;"></span>
                <?php _e('📅 Google Calendar — Configuración Global', 'aura-suite'); ?>
            </h2>
            <p class="description" style="margin-bottom:16px;">
                <?php _e('Sincroniza automáticamente eventos de mantenimiento de equipos con Google Calendar. Requiere una Service Account de Google Cloud Console con la Calendar API habilitada.', 'aura-suite'); ?>
            </p>

            <div id="js-global-gcal-msg" style="display:none;margin-bottom:12px;"></div>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Activar integración', 'aura-suite'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gcal_enabled" value="1"
                                   <?php checked(get_option('aura_gcal_enabled','0'),'1'); ?>>
                            <?php _e('Sincronizar fechas de mantenimiento con Google Calendar','aura-suite'); ?>
                        </label>
                        <p class="description"><?php _e('Requiere Service Account configurada abajo.','aura-suite'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gset-gcal-email"><?php _e('Correos para compartir calendario', 'aura-suite'); ?></label></th>
                    <td>
                        <?php $gcal_share = get_option('aura_gcal_share_email',''); ?>
                        <input type="text" name="gcal_share_email" id="gset-gcal-email" class="large-text"
                               value="<?php echo esc_attr($gcal_share); ?>"
                               placeholder="correo1@gmail.com, correo2@gmail.com">
                        <p class="description">
                            <?php _e('Correos Gmail separados por coma. Los usuarios recibirán invitación al calendario <em>Mantenimientos CEM</em>.','aura-suite'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gset-gcal-json"><?php _e('Service Account JSON', 'aura-suite'); ?></label></th>
                    <td>
                        <?php
                        $has_gcal_json = !empty(get_option('aura_gcal_service_account_json',''));
                        $gcal_stored   = $has_gcal_json ? json_decode(get_option('aura_gcal_service_account_json'), true) : [];
                        ?>
                        <?php if ($has_gcal_json): ?>
                        <div style="background:#eafaf1;border:1px solid #27ae60;padding:10px 14px;border-radius:4px;margin-bottom:8px;">
                            <span class="dashicons dashicons-yes-alt" style="color:#27ae60;"></span>
                            <strong><?php _e('Credenciales guardadas.','aura-suite'); ?></strong>
                            <?php if (!empty($gcal_stored['client_email'])): ?>
                            <code style="display:block;margin-top:4px;font-size:12px;"><?php echo esc_html($gcal_stored['client_email']); ?></code>
                            <?php endif; ?>
                            <p style="margin:6px 0 0;font-size:12px;"><?php _e('Deja el campo vacío para conservar las credenciales actuales.','aura-suite'); ?></p>
                        </div>
                        <?php endif; ?>
                        <textarea name="gcal_service_account_json" id="gset-gcal-json" class="large-text code" rows="6"
                                  placeholder="<?php esc_attr_e('Pega aquí el contenido del archivo .json descargado de Google Cloud Console...','aura-suite'); ?>"></textarea>
                        <p class="description"><?php _e('Formato: JSON completo con <code>type: service_account</code>, <code>client_email</code>, <code>private_key</code>, etc.','aura-suite'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gset-gcal-days"><?php _e('Días de recordatorio', 'aura-suite'); ?></label></th>
                    <td>
                        <input type="text" name="gcal_reminder_days" id="gset-gcal-days" class="regular-text"
                               value="<?php echo esc_attr(get_option('aura_gcal_reminder_days','15,7,3,1')); ?>"
                               placeholder="15,7,3,1">
                        <p class="description"><?php _e('Días de anticipación separados por coma (máx. 28 días). Ej: <code>15,7,3,1</code>.','aura-suite'); ?></p>
                    </td>
                </tr>
                <?php
                $gcal_resolved = get_option('aura_gcal_calendar_id_resolved','');
                if ($gcal_resolved):
                    $gcal_share_url = 'https://calendar.google.com/calendar/render?cid=' . rawurlencode($gcal_resolved);
                ?>
                <tr>
                    <th scope="row"><?php _e('Calendario activo', 'aura-suite'); ?></th>
                    <td>
                        <div style="background:#eaf4fb;border:1px solid #2c6e9e;padding:12px 16px;border-radius:4px;">
                            <p style="margin:0 0 10px;">
                                <span class="dashicons dashicons-yes-alt" style="color:#27ae60;"></span>
                                <strong>Mantenimientos CEM</strong>
                                <span style="color:#666;font-size:12px;">&nbsp;— <?php _e('creado en Google Calendar','aura-suite'); ?></span>
                            </p>
                            <a href="<?php echo esc_url($gcal_share_url); ?>" target="_blank" rel="noopener"
                               style="display:inline-flex;align-items:center;gap:6px;background:#4285f4;color:#fff;border:none;padding:7px 14px;border-radius:4px;text-decoration:none;font-size:13px;font-weight:600;">
                                <span class="dashicons dashicons-calendar-alt" style="margin:0;"></span>
                                <?php _e('Agregar a mi Google Calendar','aura-suite'); ?>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th scope="row"><?php _e('Probar conexión', 'aura-suite'); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" id="js-btn-global-gcal-test">
                            <span class="dashicons dashicons-networking"></span>
                            <?php _e('Probar conexión Google Calendar','aura-suite'); ?>
                        </button>
                        <p class="description"><?php _e('Verifica las credenciales, crea el calendario si no existe y re-envía invitaciones.','aura-suite'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Sistema -->
        <div class="aura-config-section">
            <h2><?php _e('ℹ️ Información del Sistema', 'aura-suite'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Versión de Aura', 'aura-suite'); ?></th>
                    <td><?php echo AURA_VERSION; ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Versión de WordPress', 'aura-suite'); ?></th>
                    <td><?php echo get_bloginfo('version'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Versión de PHP', 'aura-suite'); ?></th>
                    <td><?php echo phpversion(); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Usuarios Registrados', 'aura-suite'); ?></th>
                    <td><?php echo count_users()['total_users']; ?></td>
                </tr>
            </table>
        </div>
        
        <p class="submit">
            <button type="submit" name="aura_save_settings" class="button button-primary button-hero">
                <span class="dashicons dashicons-saved" style="margin-top: 6px;"></span>
                <?php _e('Guardar Configuración', 'aura-suite'); ?>
            </button>
        </p>
    </form>
</div>
<script>
jQuery(document).ready(function($) {

    var auraNonce = '<?php echo wp_create_nonce('save_aura_settings'); ?>';

    // ── WhatsApp global — visibilidad de campos según proveedor ──
    function gwaUpdateProvider() {
        var p = $('#gset-wa-provider').val();
        $('.gwa-desc-callmebot, .gwa-desc-twilio, .gwa-desc-meta').hide();
        $('.gwa-desc-' + p).show();
        $('.gwa-desc-from-callmebot, .gwa-desc-from-twilio, .gwa-desc-from-meta').hide();
        $('.gwa-desc-from-' + p).show();
        $('.gwa-row-twilio').toggle(p === 'twilio');
        $('.gwa-row-meta').toggle(p === 'meta');
    }
    gwaUpdateProvider();
    $('#gset-wa-provider').on('change', gwaUpdateProvider);

    // ── WhatsApp global — prueba de envío ─────────────────────────
    $('#js-btn-global-wa-test').on('click', function() {
        var phone = $.trim($('#gset-wa-test-phone').val());
        if (!phone) { alert('<?php echo esc_js(__('Ingresa un número de teléfono de prueba.','aura-suite')); ?>'); return; }
        var $btn = $(this).prop('disabled', true);
        var $msg = $('#js-global-wa-msg').show();
        $msg.html('<span style="color:#2271b1">⏳ <?php echo esc_js(__('Enviando...','aura-suite')); ?></span>');
        $.post(ajaxurl, { action: 'aura_global_gcal_test_whatsapp', nonce: auraNonce, phone: phone }, function(r) {
            $msg.html(r.success
                ? '<span style="color:#27ae60">✅ ' + r.data.message + '</span>'
                : '<span style="color:#c0392b">❌ ' + r.data.message + '</span>'
            );
        }).fail(function(){ $msg.html('<span style="color:#c0392b">❌ Error AJAX</span>'); })
          .always(function(){ $btn.prop('disabled', false); });
    });

    // ── Google Calendar global — probar conexión ──────────────────
    $('#js-btn-global-gcal-test').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        var $msg = $('#js-global-gcal-msg').show();
        $msg.html('<span style="color:#2271b1">⏳ <?php echo esc_js(__('Probando conexión...','aura-suite')); ?></span>');
        $.post(ajaxurl, {
            action: 'aura_global_gcal_test',
            nonce:  auraNonce,
            service_account_json: $('#gset-gcal-json').val(),
        }, function(r) {
            $msg.html(r.success
                ? '<span style="color:#27ae60">' + r.data.message + '</span>'
                : '<span style="color:#c0392b">❌ ' + r.data.message + '</span>'
            );
        }).fail(function(){ $msg.html('<span style="color:#c0392b">❌ Error AJAX</span>'); })
          .always(function(){ $btn.prop('disabled', false); });
    });
    // Toggle visibility de configuraciones de aprobación automática
    $('#auto_approval_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('#threshold_row, #application_row, #stats_row').fadeIn();
        } else {
            $('#threshold_row, #application_row, #stats_row').fadeOut();
        }
    });
    
    // Quick set threshold amounts
    $('.aura-threshold-examples a').on('click', function(e) {
        e.preventDefault();
        var amount = $(this).data('amount');
        $('#auto_approval_threshold').val(amount).focus();
    });
    
    // Validación: no permitir ambos checkboxes al mismo tiempo
    $('#apply_to_expenses_only, #apply_to_income_only').on('change', function() {
        if ($(this).is(':checked')) {
            var otherId = $(this).attr('id') === 'apply_to_expenses_only' ? 
                          '#apply_to_income_only' : '#apply_to_expenses_only';
            $(otherId).prop('checked', false);
        }
    });
    
    // Guardar excepciones de categorías (AJAX independiente)
    $('#save-category-exceptions').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $status = $('#category-save-status');
        var exceptions = [];
        
        // Recopilar categorías marcadas
        $('.category-exception:checked').each(function() {
            exceptions.push($(this).data('category-id'));
        });
        
        // Mostrar loading
        $button.prop('disabled', true);
        $status.html('<span style="color: #2271b1;">⏳ Guardando...</span>');
        
        // AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aura_save_category_exceptions',
                nonce: '<?php echo wp_create_nonce('aura_category_exceptions'); ?>',
                category_ids: exceptions
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: #10b981;">✓ ' + response.data.message + '</span>');
                    setTimeout(function() {
                        $status.fadeOut(function() {
                            $(this).html('').show();
                        });
                    }, 3000);
                } else {
                    $status.html('<span style="color: #ef4444;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $status.html('<span style="color: #ef4444;">✗ Error al guardar</span>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    $('#aura-reinstall-categories').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php echo esc_js(__('¿Está seguro que desea reinstalar las categorías predeterminadas?', 'aura-suite')); ?>')) {
            return;
        }
        
        var $button = $(this);
        var $result = $('#reinstall-result');
        
        // Deshabilitar botón y mostrar loading
        $button.prop('disabled', true);
        $button.html('<span class="dashicons dashicons-update" style="margin-top: 4px; animation: rotation 2s infinite linear;"></span> <?php echo esc_js(__('Reinstalando...', 'aura-suite')); ?>');
        $result.html('');
        
        // Hacer petición AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aura_reinstall_categories',
                nonce: '<?php echo wp_create_nonce('aura_reinstall_categories_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html(
                        '<div class="notice notice-success inline"><p>' +
                        '<strong>' + response.data.message + '</strong><br>' +
                        '<?php echo esc_js(__('Total categorías:', 'aura-suite')); ?> ' + response.data.stats.total + '<br>' +
                        '<?php echo esc_js(__('Ingresos:', 'aura-suite')); ?> ' + response.data.stats.income + ' | ' +
                        '<?php echo esc_js(__('Gastos:', 'aura-suite')); ?> ' + response.data.stats.expense +
                        '</p></div>'
                    );
                    
                    // Recargar página después de 2 segundos para actualizar stats
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.html(
                        '<div class="notice notice-error inline"><p><strong>' + 
                        response.data.message + 
                        '</strong></p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                $result.html(
                    '<div class="notice notice-error inline"><p><strong>' +
                    '<?php echo esc_js(__('Error al realizar la operación', 'aura-suite')); ?>: ' + error +
                    '</strong></p></div>'
                );
            },
            complete: function() {
                // Rehabilitar botón
                $button.prop('disabled', false);
                $button.html('<span class="dashicons dashicons-update" style="margin-top: 4px;"></span> <?php echo esc_js(__('Reinstalar Categorías Predeterminadas', 'aura-suite')); ?>');
            }
        });
    });
});
</script>

<style>
@keyframes rotation {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.aura-config-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}
.aura-config-section h2 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}
</style>