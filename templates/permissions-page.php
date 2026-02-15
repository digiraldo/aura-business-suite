<?php
/**
 * Template: Página de Gestión de Permisos
 *
 * @package AuraBusinessSuite
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('aura_admin_permissions_assign')) {
    wp_die(__('No tienes permiso para acceder a esta página.', 'aura-suite'));
}

// Procesar asignación de permisos
if (isset($_POST['aura_assign_permissions']) && isset($_POST['user_id']) && wp_verify_nonce($_POST['aura_permissions_nonce'], 'assign_permissions')) {
    $user_id = intval($_POST['user_id']);
    $user = get_user_by('id', $user_id);
    
    if ($user) {
        // Obtener todas las capabilities de Aura
        $all_caps = Aura_Roles_Manager::get_all_capabilities();
        
        // Remover todas las capabilities de Aura primero
        foreach ($all_caps as $module => $caps) {
            foreach ($caps as $cap => $desc) {
                $user->remove_cap($cap);
            }
        }
        
        // Agregar capabilities seleccionadas
        if (isset($_POST['capabilities']) && is_array($_POST['capabilities'])) {
            foreach ($_POST['capabilities'] as $capability) {
                $user->add_cap(sanitize_text_field($capability));
            }
        }
        
        echo '<div class="notice notice-success"><p>' . __('Permisos actualizados exitosamente.', 'aura-suite') . '</p></div>';
    }
}

// Obtener usuario seleccionado
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$selected_user = $selected_user_id ? get_user_by('id', $selected_user_id) : null;

// Obtener todos los usuarios
$all_users = get_users(array('orderby' => 'display_name'));

?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-admin-users" style="font-size: 32px; margin-right: 10px;"></span>
        <?php _e('Gestión de Permisos Granulares (CBAC)', 'aura-suite'); ?>
    </h1>
    
    <p class="description" style="font-size: 14px; margin-bottom: 20px;">
        <?php _e('Asigna capabilities específicas a cada usuario según sus responsabilidades. Los permisos se organizan por módulo.', 'aura-suite'); ?>
    </p>
    
    <!-- Selector de Usuario -->
    <div class="aura-config-section">
        <h2><?php _e('1️⃣ Seleccionar Usuario', 'aura-suite'); ?></h2>
        <form method="get" action="">
            <input type="hidden" name="page" value="aura-permissions">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="user_select"><?php _e('Usuario', 'aura-suite'); ?></label>
                    </th>
                    <td>
                        <select id="user_select" name="user_id" class="regular-text" onchange="this.form.submit()">
                            <option value=""><?php _e('-- Seleccionar Usuario --', 'aura-suite'); ?></option>
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo $user->ID; ?>" <?php selected($selected_user_id, $user->ID); ?>>
                                    <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </form>
    </div>
    
    <?php if ($selected_user): ?>
    
    <!-- Plantillas Predefinidas -->
    <div class="aura-config-section">
        <h2><?php _e('2️⃣ Aplicar Plantilla Predefinida (Opcional)', 'aura-suite'); ?></h2>
        <p class="description"><?php _e('Puedes cargar un perfil predefinido como punto de partida:', 'aura-suite'); ?></p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
            <?php
            $templates = Aura_Roles_Manager::get_profile_templates();
            foreach ($templates as $template_id => $template):
            ?>
            <div style="border: 2px solid #e5e7eb; border-radius: 8px; padding: 15px; cursor: pointer;" 
                 onclick="applyTemplate('<?php echo $template_id; ?>')">
                <h4 style="margin: 0 0 8px 0;"><?php echo esc_html($template['name']); ?></h4>
                <p style="margin: 0; font-size: 12px; color: #6b7280;"><?php echo esc_html($template['description']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Formulario de Permisos -->
    <form method="post" action="">
        <?php wp_nonce_field('assign_permissions', 'aura_permissions_nonce'); ?>
        <input type="hidden" name="user_id" value="<?php echo $selected_user->ID; ?>">
        
        <div class="aura-config-section">
            <h2><?php _e('3️⃣ Asignar Capabilities Individuales', 'aura-suite'); ?></h2>
            <p class="description"><?php _e('Marca los permisos específicos que necesita este usuario:', 'aura-suite'); ?></p>
            
            <div class="aura-permissions-grid">
                <?php
                $modules = Aura_Roles_Manager::get_capabilities_for_ui();
                $user_caps = $selected_user->allcaps;
                
                foreach ($modules as $module):
                ?>
                <div class="aura-permission-module">
                    <h3>
                        <span class="icon"><?php echo $module['icon']; ?></span>
                        <?php echo esc_html($module['title']); ?>
                    </h3>
                    
                    <label style="display: block; margin-bottom: 10px; font-weight: 600;">
                        <input type="checkbox" class="select-all-module" data-module="<?php echo $module['module']; ?>">
                        <?php _e('Seleccionar Todas', 'aura-suite'); ?>
                    </label>
                    
                    <div class="permission-checkboxes">
                        <?php foreach ($module['capabilities'] as $cap_name => $cap_info): ?>
                        <div class="permission-checkbox-item">
                            <input type="checkbox" 
                                   id="cap_<?php echo $cap_name; ?>" 
                                   name="capabilities[]" 
                                   value="<?php echo $cap_name; ?>"
                                   data-module="<?php echo $module['module']; ?>"
                                   <?php checked(isset($user_caps[$cap_name])); ?>>
                            <label for="cap_<?php echo $cap_name; ?>">
                                <?php echo esc_html($cap_info['label']); ?>
                                <?php if (isset($cap_info['star']) && $cap_info['star']): ?>
                                    <span style="color: #f59e0b;">⭐</span>
                                <?php endif; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <p class="submit">
            <button type="submit" name="aura_assign_permissions" class="button button-primary button-hero">
                <span class="dashicons dashicons-saved" style="margin-top: 6px;"></span>
                <?php _e('Guardar Permisos', 'aura-suite'); ?>
            </button>
        </p>
    </form>
    
    <!-- Resumen de Permisos Actuales -->
    <div class="aura-config-section">
        <h2><?php _e('Resumen de Permisos Actuales', 'aura-suite'); ?></h2>
        <p><strong><?php _e('Usuario:', 'aura-suite'); ?></strong> <?php echo $selected_user->display_name; ?></p>
        
        <?php
        $active_caps = array();
        $all_caps = Aura_Roles_Manager::get_all_capabilities();
        
        foreach ($all_caps as $module => $caps) {
            foreach ($caps as $cap => $desc) {
                if (isset($user_caps[$cap])) {
                    if (!isset($active_caps[$module])) {
                        $active_caps[$module] = array();
                    }
                    $active_caps[$module][] = $desc;
                }
            }
        }
        
        if (!empty($active_caps)):
        ?>
        <ul>
            <?php foreach ($active_caps as $module => $caps): ?>
            <li>
                <strong><?php echo ucfirst($module); ?>:</strong>
                <?php echo implode(', ', $caps); ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p><?php _e('Este usuario no tiene capabilities de Aura asignadas.', 'aura-suite'); ?></p>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Seleccionar/deseleccionar todas las capabilities de un módulo
    $('.select-all-module').on('change', function() {
        var module = $(this).data('module');
        var checked = $(this).is(':checked');
        $('input[data-module="' + module + '"]').prop('checked', checked);
    });
    
    // Aplicar plantilla predefinida
    window.applyTemplate = function(templateId) {
        if (confirm('¿Aplicar esta plantilla? Se marcarán los permisos correspondientes.')) {
            var templates = {
                'treasurer': ['aura_finance_create', 'aura_finance_edit_own', 'aura_finance_delete_own', 'aura_finance_view_own', 'aura_finance_charts'],
                'auditor': ['aura_finance_view_all', 'aura_finance_charts', 'aura_finance_export', 'aura_vehicles_view_all', 'aura_vehicles_reports', 'aura_electric_view_dashboard', 'aura_electric_view_charts', 'aura_electric_export', 'aura_forms_view_responses_all', 'aura_forms_export'],
                'field_operator': ['aura_vehicles_exits_create', 'aura_vehicles_km_update', 'aura_vehicles_view_all', 'aura_electric_reading_create', 'aura_electric_view_dashboard', 'aura_forms_submit'],
                'director': ['aura_finance_approve', 'aura_finance_view_all', 'aura_finance_charts', 'aura_finance_export', 'aura_vehicles_view_all', 'aura_vehicles_reports', 'aura_electric_view_dashboard', 'aura_electric_view_charts', 'aura_forms_view_responses_all', 'aura_forms_analytics']
            };
            
            // Desmarcar todos
            $('input[name="capabilities[]"]').prop('checked', false);
            
            // Marcar capabilities de la plantilla
            if (templates[templateId]) {
                templates[templateId].forEach(function(cap) {
                    $('#cap_' + cap).prop('checked', true);
                });
            }
        }
    };
});
</script>
