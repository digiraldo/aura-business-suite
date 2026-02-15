<?php
/**
 * Template: Dashboard Principal de Aura
 *
 * @package AuraBusinessSuite
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap aura-main-dashboard">
    <h1>
        <span style="font-size: 32px;">🌟</span>
        <?php _e('Bienvenido a Aura Business Suite', 'aura-suite'); ?>
    </h1>
    
    <p class="description" style="font-size: 16px; margin-bottom: 30px;">
        <?php _e('Sistema de Gestión Empresarial Integrado - Gestiona Finanzas, Vehículos, Formularios y Electricidad desde una sola plataforma', 'aura-suite'); ?>
    </p>
    
    <div class="aura-modules-grid">
        
        <?php if (Aura_Roles_Manager::user_can_view_module('finance')): ?>
        <a href="<?php echo admin_url('admin.php?page=aura-financial-dashboard'); ?>" class="aura-module-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <div class="module-icon">📊</div>
            <h2><?php _e('Finanzas', 'aura-suite'); ?></h2>
            <p><?php _e('Gestión de ingresos, egresos y aprobaciones', 'aura-suite'); ?></p>
            <?php
            // Mostrar cantidad de pendientes si tiene permiso de aprobar
            if (current_user_can('aura_finance_approve')) {
                $pending = count(get_posts(array(
                    'post_type' => 'aura_transaction',
                    'meta_key' => '_aura_transaction_status',
                    'meta_value' => 'pending',
                    'posts_per_page' => -1
                )));
                if ($pending > 0) {
                    echo '<div style="margin-top: 15px; background: rgba(255,255,255,0.2); padding: 8px; border-radius: 20px; display: inline-block;">';
                    echo '<strong>' . sprintf(__('%d pendientes de aprobación', 'aura-suite'), $pending) . '</strong>';
                    echo '</div>';
                }
            }
            ?>
        </a>
        <?php endif; ?>
        
        <?php if (Aura_Roles_Manager::user_can_view_module('vehicles')): ?>
        <a href="<?php echo admin_url('admin.php?page=aura-vehicle-reports'); ?>" class="aura-module-card" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
            <div class="module-icon">🚗</div>
            <h2><?php _e('Vehículos', 'aura-suite'); ?></h2>
            <p><?php _e('Control de flota, salidas y mantenimientos', 'aura-suite'); ?></p>
            <?php
            // Mostrar alertas de mantenimiento
            $alerts = Aura_Vehicle_Alerts::get_vehicles_needing_attention();
            if (!empty($alerts) && current_user_can('aura_vehicles_alerts')) {
                $critical = count(array_filter($alerts, function($a) { return $a['urgency'] === 'critical'; }));
                if ($critical > 0) {
                    echo '<div style="margin-top: 15px; background: rgba(255,255,255,0.2); padding: 8px; border-radius: 20px; display: inline-block;">';
                    echo '<strong>⚠️ ' . sprintf(__('%d alertas críticas', 'aura-suite'), $critical) . '</strong>';
                    echo '</div>';
                }
            }
            ?>
        </a>
        <?php endif; ?>
        
        <?php if (Aura_Roles_Manager::user_can_view_module('forms')): ?>
        <a href="<?php echo admin_url('edit.php?post_type=formidable'); ?>" class="aura-module-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            <div class="module-icon">📝</div>
            <h2><?php _e('Formularios', 'aura-suite'); ?></h2>
            <p><?php _e('Encuestas y recopilación de datos', 'aura-suite'); ?></p>
        </a>
        <?php endif; ?>
        
        <?php if (Aura_Roles_Manager::user_can_view_module('electricity')): ?>
        <a href="<?php echo admin_url('admin.php?page=aura-electricity-dashboard'); ?>" class="aura-module-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
            <div class="module-icon">⚡</div>
            <h2><?php _e('Electricidad', 'aura-suite'); ?></h2>
            <p><?php _e('Monitoreo de consumo y alertas', 'aura-suite'); ?></p>
        </a>
        <?php endif; ?>
        
        <?php if (current_user_can('aura_admin_settings')): ?>
        <a href="<?php echo admin_url('admin.php?page=aura-settings'); ?>" class="aura-module-card" style="background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);">
            <div class="module-icon">⚙️</div>
            <h2><?php _e('Configuración', 'aura-suite'); ?></h2>
            <p><?php _e('Ajustes del sistema y preferencias', 'aura-suite'); ?></p>
        </a>
        <?php endif; ?>
        
        <?php if (current_user_can('aura_admin_permissions_assign')): ?>
        <a href="<?php echo admin_url('admin.php?page=aura-permissions'); ?>" class="aura-module-card" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
            <div class="module-icon">🔐</div>
            <h2><?php _e('Permisos', 'aura-suite'); ?></h2>
            <p><?php _e('Gestión de capabilities por usuario', 'aura-suite'); ?></p>
        </a>
        <?php endif; ?>
    </div>
    
    <!-- Resumen rápido -->
    <div class="aura-dashboard-grid" style="margin-top: 40px;">
        <div class="aura-chart-container">
            <h2><?php _e('Accesos Rápidos', 'aura-suite'); ?></h2>
            <ul style="list-style: none; padding: 0;">
                <?php if (current_user_can('aura_finance_create')): ?>
                <li style="margin: 10px 0;">
                    <a href="<?php echo admin_url('post-new.php?post_type=aura_transaction'); ?>" class="button button-primary">
                        <span class="dashicons dashicons-plus" style="margin-top: 4px;"></span>
                        <?php _e('Nueva Transacción Financiera', 'aura-suite'); ?>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (current_user_can('aura_vehicles_exits_create')): ?>
                <li style="margin: 10px 0;">
                    <a href="<?php echo admin_url('post-new.php?post_type=aura_vehicle_exit'); ?>" class="button button-primary">
                        <span class="dashicons dashicons-plus" style="margin-top: 4px;"></span>
                        <?php _e('Registrar Salida de Vehículo', 'aura-suite'); ?>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (current_user_can('aura_electric_reading_create')): ?>
                <li style="margin: 10px 0;">
                    <a href="<?php echo admin_url('post-new.php?post_type=aura_electric_reading'); ?>" class="button button-primary">
                        <span class="dashicons dashicons-plus" style="margin-top: 4px;"></span>
                        <?php _e('Registrar Lectura Eléctrica', 'aura-suite'); ?>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="aura-chart-container">
            <h2><?php _e('Información del Sistema', 'aura-suite'); ?></h2>
            <table style="width: 100%;">
                <tr>
                    <td><strong><?php _e('Versión:', 'aura-suite'); ?></strong></td>
                    <td><?php echo AURA_VERSION; ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Usuario:', 'aura-suite'); ?></strong></td>
                    <td><?php echo wp_get_current_user()->display_name; ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Rol:', 'aura-suite'); ?></strong></td>
                    <td>
                        <?php 
                        $user = wp_get_current_user();
                        echo !empty($user->roles) ? ucfirst($user->roles[0]) : __('Usuario', 'aura-suite');
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php _e('Módulos Activos:', 'aura-suite'); ?></strong></td>
                    <td>
                        <?php 
                        $active_modules = 0;
                        if (Aura_Roles_Manager::user_can_view_module('finance')) $active_modules++;
                        if (Aura_Roles_Manager::user_can_view_module('vehicles')) $active_modules++;
                        if (Aura_Roles_Manager::user_can_view_module('forms')) $active_modules++;
                        if (Aura_Roles_Manager::user_can_view_module('electricity')) $active_modules++;
                        echo $active_modules . ' / 4';
                        ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Documentación y Soporte -->
    <div class="aura-chart-container" style="margin-top: 20px;">
        <h2><?php _e('Recursos y Soporte', 'aura-suite'); ?></h2>
        <p><?php _e('Para más información sobre cómo usar Aura Business Suite:', 'aura-suite'); ?></p>
        <ul>
            <li><a href="#" target="_blank"><?php _e('📖 Documentación Completa', 'aura-suite'); ?></a></li>
            <li><a href="#" target="_blank"><?php _e('🎥 Tutoriales en Video', 'aura-suite'); ?></a></li>
            <li><a href="#" target="_blank"><?php _e('💬 Soporte Técnico', 'aura-suite'); ?></a></li>
            <li><a href="<?php echo admin_url('admin.php?page=aura-settings'); ?>"><?php _e('⚙️ Configuración del Sistema', 'aura-suite'); ?></a></li>
        </ul>
    </div>
</div>
