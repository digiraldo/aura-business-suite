<?php
/**
 * Gestor de Roles y Capabilities (CBAC)
 * 
 * Sistema de permisos granulares basado en capabilities individuales por usuario
 * No usa roles fijos predefinidos, sino capabilities asignadas directamente
 *
 * @package AuraBusinessSuite
 * @subpackage Common
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar capabilities granulares por módulo
 */
class Aura_Roles_Manager {
    
    /**
     * Inicializar el gestor de roles
     */
    public static function init() {
        // Hook para agregar capabilities a la instalación
        add_action('init', array(__CLASS__, 'maybe_add_capabilities'));
        
        // Hook para restringir acceso en el admin
        add_action('admin_init', array(__CLASS__, 'restrict_admin_access'));
    }
    
    /**
     * Registrar todas las capabilities en el sistema
     */
    public static function register_all_capabilities() {
        $capabilities = self::get_all_capabilities();
        
        // Agregar capabilities al rol de Administrador
        $admin_role = get_role('administrator');
        
        if ($admin_role) {
            foreach ($capabilities as $module => $caps) {
                foreach ($caps as $cap => $description) {
                    $admin_role->add_cap($cap);
                }
            }
        }
    }
    
    /**
     * Verificar y agregar capabilities si es necesario
     */
    public static function maybe_add_capabilities() {
        $version_option = 'aura_capabilities_version';
        $current_version = get_option($version_option, '0');
        
        // Solo agregar si no se han registrado o si hay nueva versión
        if (version_compare($current_version, AURA_VERSION, '<')) {
            self::register_all_capabilities();
            update_option($version_option, AURA_VERSION);
        }
    }
    
    /**
     * Obtener todas las capabilities organizadas por módulo
     * 
     * @return array Array de capabilities por módulo
     */
    public static function get_all_capabilities() {
        return array(
            'finance' => array(
                'aura_finance_create'      => __('Crear transacciones financieras', 'aura-suite'),
                'aura_finance_edit_own'    => __('Editar propias transacciones', 'aura-suite'),
                'aura_finance_edit_all'    => __('Editar todas las transacciones', 'aura-suite'),
                'aura_finance_delete_own'  => __('Eliminar propias transacciones', 'aura-suite'),
                'aura_finance_delete_all'  => __('Eliminar cualquier transacción', 'aura-suite'),
                'aura_finance_approve'     => __('Aprobar/rechazar gastos', 'aura-suite'),
                'aura_finance_view_own'    => __('Ver solo transacciones propias', 'aura-suite'),
                'aura_finance_view_all'    => __('Ver todas las transacciones', 'aura-suite'),
                'aura_finance_charts'      => __('Ver gráficos financieros', 'aura-suite'),
                'aura_finance_export'      => __('Exportar reportes financieros', 'aura-suite'),
                'aura_finance_category_manage' => __('Gestionar categorías financieras', 'aura-suite'),
            ),
            'vehicles' => array(
                'aura_vehicles_create'          => __('Crear/registrar vehículos', 'aura-suite'),
                'aura_vehicles_edit'            => __('Editar vehículos', 'aura-suite'),
                'aura_vehicles_delete'          => __('Eliminar vehículos', 'aura-suite'),
                'aura_vehicles_exits_create'    => __('Registrar salidas', 'aura-suite'),
                'aura_vehicles_exits_edit_own'  => __('Editar propias salidas', 'aura-suite'),
                'aura_vehicles_exits_edit_all'  => __('Editar todas las salidas', 'aura-suite'),
                'aura_vehicles_km_update'       => __('Actualizar kilometraje', 'aura-suite'),
                'aura_vehicles_view_all'        => __('Ver todos los vehículos', 'aura-suite'),
                'aura_vehicles_reports'         => __('Ver reportes de vehículos', 'aura-suite'),
                'aura_vehicles_alerts'          => __('Recibir alertas de mantenimiento', 'aura-suite'),
            ),
            'forms' => array(
                'aura_forms_submit'             => __('Llenar formularios', 'aura-suite'),
                'aura_forms_create'             => __('Crear formularios', 'aura-suite'),
                'aura_forms_edit'               => __('Editar formularios', 'aura-suite'),
                'aura_forms_delete'             => __('Eliminar formularios', 'aura-suite'),
                'aura_forms_view_responses_own' => __('Ver respuestas propias', 'aura-suite'),
                'aura_forms_view_responses_all' => __('Ver todas las respuestas', 'aura-suite'),
                'aura_forms_export'             => __('Exportar respuestas', 'aura-suite'),
                'aura_forms_analytics'          => __('Ver análisis y gráficos de encuestas', 'aura-suite'),
            ),
            'electricity' => array(
                'aura_electric_reading_create'     => __('Registrar lecturas', 'aura-suite'),
                'aura_electric_reading_edit_own'   => __('Editar propias lecturas', 'aura-suite'),
                'aura_electric_reading_edit_all'   => __('Editar todas las lecturas', 'aura-suite'),
                'aura_electric_reading_delete'     => __('Eliminar lecturas', 'aura-suite'),
                'aura_electric_view_dashboard'     => __('Ver dashboard de consumo', 'aura-suite'),
                'aura_electric_view_charts'        => __('Ver gráficos de tendencias', 'aura-suite'),
                'aura_electric_alerts_receive'     => __('Recibir alertas de consumo alto', 'aura-suite'),
                'aura_electric_thresholds_config'  => __('Configurar umbrales de alerta', 'aura-suite'),
                'aura_electric_export'             => __('Exportar datos de consumo', 'aura-suite'),
            ),
            'admin' => array(
                'aura_admin_users_manage'       => __('Gestionar usuarios', 'aura-suite'),
                'aura_admin_permissions_assign' => __('Asignar permisos', 'aura-suite'),
                'aura_admin_settings'           => __('Configurar sistema', 'aura-suite'),
                'aura_admin_modules_enable'     => __('Activar/desactivar módulos', 'aura-suite'),
                'aura_admin_backup'             => __('Gestionar backups', 'aura-suite'),
                'aura_admin_logs'               => __('Ver logs de auditoría', 'aura-suite'),
            ),
        );
    }
    
    /**
     * Obtener capabilities agrupadas para la UI de permisos
     * 
     * @return array Array con información detallada de capabilities
     */
    public static function get_capabilities_for_ui() {
        return array(
            array(
                'module'       => 'finance',
                'icon'         => '📊',
                'title'        => __('MÓDULO: FINANZAS', 'aura-suite'),
                'capabilities' => array(
                    'aura_finance_create'      => array('label' => __('Crear transacciones', 'aura-suite'), 'code' => 'create'),
                    'aura_finance_edit_own'    => array('label' => __('Editar propias', 'aura-suite'), 'code' => 'edit_own'),
                    'aura_finance_edit_all'    => array('label' => __('Editar todas', 'aura-suite'), 'code' => 'edit_all'),
                    'aura_finance_delete_own'  => array('label' => __('Eliminar propias', 'aura-suite'), 'code' => 'delete_own'),
                    'aura_finance_delete_all'  => array('label' => __('Eliminar todas', 'aura-suite'), 'code' => 'delete_all'),
                    'aura_finance_approve'     => array('label' => __('Aprobar gastos', 'aura-suite'), 'code' => 'approve', 'star' => true),
                    'aura_finance_view_own'    => array('label' => __('Ver solo propias', 'aura-suite'), 'code' => 'view_own'),
                    'aura_finance_view_all'    => array('label' => __('Ver todas', 'aura-suite'), 'code' => 'view_all'),
                    'aura_finance_charts'      => array('label' => __('Ver gráficos', 'aura-suite'), 'code' => 'charts'),
                    'aura_finance_export'      => array('label' => __('Exportar reportes', 'aura-suite'), 'code' => 'export'),
                    'aura_finance_category_manage' => array('label' => __('Gestionar categorías', 'aura-suite'), 'code' => 'category_manage'),
                ),
            ),
            array(
                'module'       => 'vehicles',
                'icon'         => '🚗',
                'title'        => __('MÓDULO: VEHÍCULOS', 'aura-suite'),
                'capabilities' => array(
                    'aura_vehicles_create'         => array('label' => __('Crear/editar vehículos', 'aura-suite'), 'code' => 'create/edit'),
                    'aura_vehicles_delete'         => array('label' => __('Eliminar vehículos', 'aura-suite'), 'code' => 'delete'),
                    'aura_vehicles_exits_create'   => array('label' => __('Registrar salidas', 'aura-suite'), 'code' => 'exits_create'),
                    'aura_vehicles_exits_edit_own' => array('label' => __('Editar propias salidas', 'aura-suite'), 'code' => 'exits_edit_own'),
                    'aura_vehicles_exits_edit_all' => array('label' => __('Editar todas las salidas', 'aura-suite'), 'code' => 'exits_edit_all'),
                    'aura_vehicles_km_update'      => array('label' => __('Actualizar kilometraje', 'aura-suite'), 'code' => 'km_update'),
                    'aura_vehicles_view_all'       => array('label' => __('Ver todos los vehículos', 'aura-suite'), 'code' => 'view_all'),
                    'aura_vehicles_reports'        => array('label' => __('Ver reportes', 'aura-suite'), 'code' => 'reports'),
                    'aura_vehicles_alerts'         => array('label' => __('Recibir alertas', 'aura-suite'), 'code' => 'alerts'),
                ),
            ),
            array(
                'module'       => 'forms',
                'icon'         => '📝',
                'title'        => __('MÓDULO: FORMULARIOS', 'aura-suite'),
                'capabilities' => array(
                    'aura_forms_submit'             => array('label' => __('Llenar formularios', 'aura-suite'), 'code' => 'submit'),
                    'aura_forms_create'             => array('label' => __('Crear formularios', 'aura-suite'), 'code' => 'create'),
                    'aura_forms_edit'               => array('label' => __('Editar formularios', 'aura-suite'), 'code' => 'edit'),
                    'aura_forms_delete'             => array('label' => __('Eliminar formularios', 'aura-suite'), 'code' => 'delete'),
                    'aura_forms_view_responses_all' => array('label' => __('Ver todas respuestas', 'aura-suite'), 'code' => 'view_all'),
                    'aura_forms_export'             => array('label' => __('Exportar respuestas', 'aura-suite'), 'code' => 'export'),
                    'aura_forms_analytics'          => array('label' => __('Ver análisis', 'aura-suite'), 'code' => 'analytics'),
                ),
            ),
            array(
                'module'       => 'electricity',
                'icon'         => '⚡',
                'title'        => __('MÓDULO: ELECTRICIDAD', 'aura-suite'),
                'capabilities' => array(
                    'aura_electric_reading_create'    => array('label' => __('Registrar lecturas', 'aura-suite'), 'code' => 'reading_create'),
                    'aura_electric_reading_edit_own'  => array('label' => __('Editar propias lecturas', 'aura-suite'), 'code' => 'reading_edit_own'),
                    'aura_electric_reading_edit_all'  => array('label' => __('Editar todas las lecturas', 'aura-suite'), 'code' => 'reading_edit_all'),
                    'aura_electric_view_dashboard'    => array('label' => __('Ver dashboard', 'aura-suite'), 'code' => 'view_dashboard'),
                    'aura_electric_view_charts'       => array('label' => __('Ver gráficos', 'aura-suite'), 'code' => 'view_charts'),
                    'aura_electric_alerts_receive'    => array('label' => __('Recibir alertas', 'aura-suite'), 'code' => 'alerts_receive'),
                    'aura_electric_thresholds_config' => array('label' => __('Configurar alertas', 'aura-suite'), 'code' => 'config_alerts'),
                    'aura_electric_export'            => array('label' => __('Exportar datos', 'aura-suite'), 'code' => 'export'),
                ),
            ),
            array(
                'module'       => 'admin',
                'icon'         => '⚙️',
                'title'        => __('MÓDULO: ADMINISTRACIÓN', 'aura-suite'),
                'capabilities' => array(
                    'aura_admin_users_manage'       => array('label' => __('Gestionar usuarios', 'aura-suite'), 'code' => 'users_manage'),
                    'aura_admin_permissions_assign' => array('label' => __('Asignar permisos', 'aura-suite'), 'code' => 'permissions_assign'),
                    'aura_admin_settings'           => array('label' => __('Configurar sistema', 'aura-suite'), 'code' => 'settings'),
                    'aura_admin_modules_enable'     => array('label' => __('Activar/desactivar módulos', 'aura-suite'), 'code' => 'modules_enable'),
                    'aura_admin_backup'             => array('label' => __('Gestionar backups', 'aura-suite'), 'code' => 'backup'),
                    'aura_admin_logs'               => array('label' => __('Ver logs de auditoría', 'aura-suite'), 'code' => 'logs'),
                ),
            ),
        );
    }
    
    /**
     * Obtener plantillas de perfiles predefinidos
     * 
     * @return array Array de plantillas
     */
    public static function get_profile_templates() {
        return array(
            'treasurer' => array(
                'name'         => __('Tesorero Base', 'aura-suite'),
                'description'  => __('Puede crear y editar transacciones propias, ver gráficos', 'aura-suite'),
                'capabilities' => array(
                    'aura_finance_create',
                    'aura_finance_edit_own',
                    'aura_finance_delete_own',
                    'aura_finance_view_own',
                    'aura_finance_charts',
                ),
            ),
            'auditor' => array(
                'name'         => __('Auditor General', 'aura-suite'),
                'description'  => __('Acceso de solo lectura a todos los módulos', 'aura-suite'),
                'capabilities' => array(
                    'aura_finance_view_all',
                    'aura_finance_charts',
                    'aura_finance_export',
                    'aura_vehicles_view_all',
                    'aura_vehicles_reports',
                    'aura_electric_view_dashboard',
                    'aura_electric_view_charts',
                    'aura_electric_export',
                    'aura_forms_view_responses_all',
                    'aura_forms_export',
                ),
            ),
            'field_operator' => array(
                'name'         => __('Operador de Campo', 'aura-suite'),
                'description'  => __('Acceso a vehículos y electricidad', 'aura-suite'),
                'capabilities' => array(
                    'aura_vehicles_exits_create',
                    'aura_vehicles_km_update',
                    'aura_vehicles_view_all',
                    'aura_electric_reading_create',
                    'aura_electric_view_dashboard',
                    'aura_forms_submit',
                ),
            ),
            'director' => array(
                'name'         => __('Director General', 'aura-suite'),
                'description'  => __('Acceso completo de visualización y aprobaciones', 'aura-suite'),
                'capabilities' => array(
                    'aura_finance_approve',
                    'aura_finance_view_all',
                    'aura_finance_charts',
                    'aura_finance_export',
                    'aura_vehicles_view_all',
                    'aura_vehicles_reports',
                    'aura_electric_view_dashboard',
                    'aura_electric_view_charts',
                    'aura_forms_view_responses_all',
                    'aura_forms_analytics',
                ),
            ),
        );
    }
    
    /**
     * Asignar plantilla de perfil a usuario
     * 
     * @param int    $user_id     ID del usuario
     * @param string $template_id ID de la plantilla
     * @return bool
     */
    public static function assign_template_to_user($user_id, $template_id) {
        $templates = self::get_profile_templates();
        
        if (!isset($templates[$template_id])) {
            return false;
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $capabilities = $templates[$template_id]['capabilities'];
        
        foreach ($capabilities as $cap) {
            $user->add_cap($cap);
        }
        
        return true;
    }
    
    /**
     * Restringir acceso al admin según capabilities
     */
    public static function restrict_admin_access() {
        $current_user = wp_get_current_user();
        
        // Permitir acceso a administradores y usuarios con alguna capability de Aura
        if (current_user_can('administrator') || self::user_has_any_aura_capability()) {
            return;
        }
        
        // Redirigir usuarios sin permisos
        if (!current_user_can('read')) {
            wp_redirect(home_url());
            exit;
        }
    }
    
    /**
     * Verificar si el usuario tiene alguna capability de Aura
     * 
     * @return bool
     */
    public static function user_has_any_aura_capability() {
        $all_caps = self::get_all_capabilities();
        
        foreach ($all_caps as $module => $caps) {
            foreach ($caps as $cap => $description) {
                if (current_user_can($cap)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Verificar si el usuario puede ver un módulo específico
     * 
     * @param string $module Nombre del módulo
     * @return bool
     */
    public static function user_can_view_module($module) {
        $all_caps = self::get_all_capabilities();
        
        if (!isset($all_caps[$module])) {
            return false;
        }
        
        foreach ($all_caps[$module] as $cap => $description) {
            if (current_user_can($cap)) {
                return true;
            }
        }
        
        return false;
    }
}
