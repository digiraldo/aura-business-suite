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
    
    // Configuración de electricidad
    update_option('aura_electric_threshold', floatval($_POST['electric_threshold']));
    update_option('aura_electric_cost_kwh', floatval($_POST['electric_cost_kwh']));
    
    // Configuración de vehículos
    update_option('aura_vehicle_maintenance_interval', intval($_POST['vehicle_maintenance_interval']));
    update_option('aura_vehicle_alert_threshold', intval($_POST['vehicle_alert_threshold']));
    
    echo '<div class="notice notice-success"><p>' . __('Configuración guardada exitosamente.', 'aura-suite') . '</p></div>';
}

// Obtener configuración actual
$notification_email = get_option('aura_notification_email', get_option('admin_email'));
$notification_from_name = get_option('aura_notification_from_name', get_bloginfo('name'));
$electric_threshold = get_option('aura_electric_threshold', 500);
$electric_cost_kwh = get_option('aura_electric_cost_kwh', 0.12);
$vehicle_maintenance_interval = get_option('aura_vehicle_maintenance_interval', 5000);
$vehicle_alert_threshold = get_option('aura_vehicle_alert_threshold', 500);

?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-admin-settings" style="font-size: 32px; margin-right: 10px;"></span>
        <?php _e('Configuración de Aura Business Suite', 'aura-suite'); ?>
    </h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('save_aura_settings', 'aura_settings_nonce'); ?>
        
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