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
        // Hook para agregar capabilities a la instalación (basado en versión)
        add_action('init', array(__CLASS__, 'maybe_add_capabilities'));

        // Garantizar que el administrador tenga TODAS las capabilities en cada carga
        // del admin (solo escribe a BD cuando falta alguna, no genera sobrecarga)
        add_action('admin_init', array(__CLASS__, 'ensure_admin_capabilities'));
        
        // Hook para restringir acceso en el admin
        add_action('admin_init', array(__CLASS__, 'restrict_admin_access'));

        // Asegurar que los usuarios con permisos de Aura lleguen al dashboard
        // después de iniciar sesión, aunque WooCommerce u otro plugin intente
        // mandarlos a /mi-cuenta/.
        add_filter('login_redirect', array(__CLASS__, 'redirect_aura_users_to_admin'), 999, 3);
        add_filter('woocommerce_login_redirect', array(__CLASS__, 'redirect_aura_users_to_admin_wc'), 999, 2);
        add_filter('woocommerce_prevent_admin_access', array(__CLASS__, 'allow_aura_users_admin_access'));
        add_action('template_redirect', array(__CLASS__, 'maybe_redirect_aura_users_from_account_page'), 1);

        // Conceder upload_files dinámicamente a usuarios con caps de módulos que manejan imágenes
        add_filter('user_has_cap', array(__CLASS__, 'grant_upload_cap_for_media_modules'), 10, 4);

        // Permitir ver todos los medios del sistema (no solo los propios) a esos mismos usuarios
        add_filter('ajax_query_attachments_args', array(__CLASS__, 'allow_view_all_media_for_modules'));
    }
    
    /**
     * Registrar todas las capabilities en el sistema
     */
    public static function register_all_capabilities() {
        $capabilities = self::get_all_capabilities();
        
        // Agregar capabilities al rol de Administrador (solo las que aún no tiene)
        $admin_role = get_role('administrator');
        
        if ($admin_role) {
            foreach ($capabilities as $module => $caps) {
                foreach ($caps as $cap => $description) {
                    if ( ! isset( $admin_role->capabilities[ $cap ] ) ) {
                        $admin_role->add_cap( $cap );
                    }
                }
            }
        }
    }

    /**
     * Forzar asignación de capabilities faltantes al administrador,
     * independientemente del número de versión almacenado.
     * Se llama en cada carga del plugin para garantizar que nuevas
     * capabilities añadidas en actualizaciones siempre se registren.
     */
    public static function ensure_admin_capabilities() {
        $capabilities = self::get_all_capabilities();
        $admin_role   = get_role( 'administrator' );

        if ( ! $admin_role ) {
            return;
        }

        foreach ( $capabilities as $module => $caps ) {
            foreach ( $caps as $cap => $description ) {
                if ( ! isset( $admin_role->capabilities[ $cap ] ) ) {
                    $admin_role->add_cap( $cap );
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
                'aura_finance_category_manage' => __('Gestionar categorías de gastos/ingresos (ej: Suministros, Salarios)', 'aura-suite'),
                // Fase 6: Vinculación de Usuarios
                'aura_finance_link_user'           => __('Vincular usuario del sistema a una transacción', 'aura-suite'),
                'aura_finance_user_ledger'         => __('Ver libro mayor agrupado por usuario', 'aura-suite'),
                'aura_finance_view_user_summary'   => __('Ver propio dashboard financiero personal', 'aura-suite'),
                'aura_finance_view_others_summary' => __('Ver dashboard financiero de otros usuarios', 'aura-suite'),
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
                'aura_vehicles_audit'           => __('Ver auditoría de vehículos', 'aura-suite'),
                'aura_vehicles_settings'        => __('Configurar módulo de vehículos', 'aura-suite'),
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
                'aura_forms_assign'             => __('Asignar encuestas a estudiantes', 'aura-suite'),
                'aura_forms_enrollment_review'  => __('Revisar postulantes de inscripción', 'aura-suite'),
                'aura_forms_settings'           => __('Configurar el módulo de formularios', 'aura-suite'),
                'aura_forms_reports'            => __('Ver reportes de formularios', 'aura-suite'),
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
            // Módulo de Biblioteca
            'library' => array(
                'aura_library_access'           => __('Acceder al módulo de biblioteca', 'aura-suite'),
                'aura_library_create'           => __('Agregar libros al catálogo', 'aura-suite'),
                'aura_library_edit'             => __('Editar información de libros', 'aura-suite'),
                'aura_library_delete'           => __('Eliminar libros del catálogo', 'aura-suite'),
                'aura_library_view_catalog'     => __('Ver catálogo completo de libros', 'aura-suite'),
                'aura_library_loan_create'      => __('Registrar préstamo de libro', 'aura-suite'),
                'aura_library_loan_return'      => __('Registrar devolución de libro', 'aura-suite'),
                'aura_library_loan_extend'      => __('Extender plazo de préstamo', 'aura-suite'),
                'aura_library_loan_edit'        => __('Editar datos de un préstamo', 'aura-suite'),
                'aura_library_loan_delete'      => __('Cancelar/eliminar un préstamo', 'aura-suite'),
                'aura_library_view_loans_own'   => __('Ver solo préstamos propios', 'aura-suite'),
                'aura_library_view_loans_all'   => __('Ver todos los préstamos activos', 'aura-suite'),
                'aura_library_reports'          => __('Ver reportes de biblioteca', 'aura-suite'),
                'aura_library_alerts'           => __('Recibir alertas de devoluciones vencidas', 'aura-suite'),
                'aura_library_settings'         => __('Configurar módulo de biblioteca', 'aura-suite'),
                'aura_library_audit'            => __('Ver auditoría de biblioteca', 'aura-suite'),
            ),
            'admin' => array(
                'aura_admin_users_create'       => __('Crear nuevos usuarios (como suscriptor)', 'aura-suite'),
                'aura_admin_users_manage'       => __('Gestionar usuarios existentes', 'aura-suite'),
                'aura_admin_permissions_assign' => __('Asignar permisos a usuarios', 'aura-suite'),
                'aura_admin_settings'           => __('Configurar sistema', 'aura-suite'),
                'aura_admin_modules_enable'     => __('Activar/desactivar módulos', 'aura-suite'),
                'aura_admin_backup'             => __('Gestionar backups', 'aura-suite'),
                'aura_admin_logs'               => __('Ver logs de auditoría', 'aura-suite'),
            ),
            // Fase 7 — Módulo de Áreas y Programas
            'areas' => array(
                'aura_areas_manage'             => __('Gestionar áreas y programas', 'aura-suite'),
                'aura_areas_types_manage'       => __('Gestionar tipos de área', 'aura-suite'),
                'aura_areas_view_all'           => __('Ver todas las áreas', 'aura-suite'),
                'aura_areas_view_own'           => __('Ver solo área asignada como responsable', 'aura-suite'),
                'aura_areas_budget_manage'      => __('Gestionar presupuesto de área', 'aura-suite'),
                'aura_areas_budget_view'        => __('Ver presupuesto de área', 'aura-suite'),
                'aura_areas_assign_user'        => __('Asignar responsable a área', 'aura-suite'),
                'aura_areas_forms_manage'       => __('Crear formularios propios del área', 'aura-suite'),
                'aura_areas_enrollment_manage'  => __('Gestionar inscripciones del área', 'aura-suite'),
            ),
            // Módulo de Inventario y Mantenimientos
            'inventory' => array(
                'aura_inventory_create'               => __('Crear/registrar equipos y herramientas', 'aura-suite'),
                'aura_inventory_edit'                 => __('Editar datos de equipos', 'aura-suite'),
                'aura_inventory_delete'               => __('Eliminar equipos del inventario', 'aura-suite'),
                'aura_inventory_view_all'             => __('Ver todo el inventario', 'aura-suite'),
                'aura_inventory_checkout'             => __('Registrar préstamo/salida de equipos', 'aura-suite'),
                'aura_inventory_checkin'              => __('Registrar devolución de equipos', 'aura-suite'),
                'aura_inventory_loan_edit'             => __('Editar registros de préstamos', 'aura-suite'),
                'aura_inventory_loan_delete'           => __('Eliminar registros de préstamos', 'aura-suite'),
                'aura_inventory_maintenance_create'   => __('Registrar mantenimiento realizado', 'aura-suite'),
                'aura_inventory_maintenance_edit'     => __('Editar registros de mantenimiento', 'aura-suite'),
                'aura_inventory_maintenance_delete'   => __('Eliminar registros de mantenimiento', 'aura-suite'),
                'aura_inventory_maintenance_schedule' => __('Configurar calendarios de mantenimiento', 'aura-suite'),
                'aura_inventory_maintenance_view'     => __('Ver historial de mantenimientos', 'aura-suite'),
                'aura_inventory_maintenance_alerts'   => __('Recibir notificaciones de mantenimientos', 'aura-suite'),
                'aura_inventory_maintenance_external' => __('Registrar servicios en talleres externos', 'aura-suite'),
                'aura_inventory_stock_min'            => __('Configurar stock mínimo y alertas', 'aura-suite'),
                'aura_inventory_reports'              => __('Ver reportes de disponibilidad y uso', 'aura-suite'),
                'aura_inventory_categories'           => __('Gestionar categorías de inventario', 'aura-suite'),
                'aura_inventory_cost_tracking'        => __('Ver costos de mantenimiento por equipo', 'aura-suite'),
                'aura_inventory_lifecycle'            => __('Ver vida útil y depreciación de equipos', 'aura-suite'),
            ),
            // Módulo de Certificados y Diplomas
            'certificates' => array(
                'aura_cert_template_view'     => __('Ver plantillas de certificados', 'aura-suite'),
                'aura_cert_template_create'   => __('Crear nuevas plantillas de diseño', 'aura-suite'),
                'aura_cert_template_edit'     => __('Editar plantillas existentes', 'aura-suite'),
                'aura_cert_template_delete'   => __('Eliminar plantillas (solo admin)', 'aura-suite'),
                'aura_cert_issue'             => __('Emitir certificados a estudiantes', 'aura-suite'),
                'aura_cert_revoke'            => __('Revocar certificados emitidos (solo admin)', 'aura-suite'),
                'aura_cert_view_all'          => __('Ver listado completo de certificados emitidos', 'aura-suite'),
                'aura_cert_download_any'      => __('Descargar PDF de cualquier estudiante', 'aura-suite'),
                'aura_cert_download_own'      => __('Descargar solo el propio certificado (estudiante)', 'aura-suite'),
                'aura_cert_signatures_manage' => __('Gestionar firmantes y sus firmas', 'aura-suite'),
                'aura_cert_verify_public'     => __('Verificar autenticidad vía página pública', 'aura-suite'),
                'aura_cert_settings'          => __('Configurar el módulo de certificados', 'aura-suite'),
                'aura_cert_reports'           => __('Ver reportes de certificados emitidos', 'aura-suite'),
            ),
            // Módulo de Estudiantes e Inscripciones
            'students' => array(
                'aura_students_create'              => __('Crear/registrar estudiantes manualmente', 'aura-suite'),
                'aura_students_edit'                => __('Editar información de estudiantes', 'aura-suite'),
                'aura_students_delete'              => __('Eliminar estudiantes (solo admin)', 'aura-suite'),
                'aura_students_view_all'            => __('Ver todos los estudiantes', 'aura-suite'),
                'aura_students_view_own'            => __('Ver solo información propia (estudiante)', 'aura-suite'),
                'aura_students_approve'             => __('Aprobar/rechazar solicitudes de inscripción', 'aura-suite'),
                'aura_students_enrollments_manage'  => __('Gestionar inscripciones a cursos', 'aura-suite'),
                'aura_students_scholarships_view'   => __('Ver becas asignadas', 'aura-suite'),
                'aura_students_scholarships_assign' => __('Asignar/modificar becas', 'aura-suite'),
                'aura_students_payments_register'   => __('Registrar pagos de estudiantes', 'aura-suite'),
                'aura_students_payments_view_all'   => __('Ver estado de pagos de todos', 'aura-suite'),
                'aura_students_payments_view_own'   => __('Ver solo pagos propios (estudiante)', 'aura-suite'),
                'aura_students_quotas_config'       => __('Configurar esquemas de cuotas', 'aura-suite'),
                'aura_students_status_view'         => __('Ver estado paz y salvo', 'aura-suite'),
                'aura_students_courses_manage'      => __('Crear y gestionar cursos/programas', 'aura-suite'),
                'aura_students_reports'             => __('Ver reportes de inscripciones y pagos', 'aura-suite'),
                'aura_students_settings'            => __('Configurar el módulo de estudiantes', 'aura-suite'),
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
            // 1 — Finanzas (orden dashboard principal)
            array(
                'module'       => 'finance',
                'icon'         => '💰',
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
                    'aura_finance_link_user'           => array('label' => __('Vincular usuario a transacción', 'aura-suite'), 'code' => 'link_user'),
                    'aura_finance_user_ledger'         => array('label' => __('Ver libro mayor por usuario', 'aura-suite'), 'code' => 'user_ledger'),
                    'aura_finance_view_user_summary'   => array('label' => __('Ver mi dashboard financiero personal', 'aura-suite'), 'code' => 'view_user_summary'),
                    'aura_finance_view_others_summary' => array('label' => __('Ver dashboard financiero de otros usuarios', 'aura-suite'), 'code' => 'view_others_summary', 'star' => true),
                ),
            ),
            // 2 — Inventario y Mantenimientos
            array(
                'module'       => 'inventory',
                'icon'         => '📦',
                'title'        => __('MÓDULO: INVENTARIO Y MANTENIMIENTOS', 'aura-suite'),
                'capabilities' => array(
                    'aura_inventory_create'               => array('label' => __('Registrar equipos', 'aura-suite'), 'code' => 'create'),
                    'aura_inventory_edit'                 => array('label' => __('Editar equipos', 'aura-suite'), 'code' => 'edit'),
                    'aura_inventory_delete'               => array('label' => __('Eliminar equipos', 'aura-suite'), 'code' => 'delete'),
                    'aura_inventory_view_all'             => array('label' => __('Ver todo el inventario', 'aura-suite'), 'code' => 'view_all'),
                    'aura_inventory_checkout'             => array('label' => __('Registrar préstamo (salida)', 'aura-suite'), 'code' => 'checkout'),
                    'aura_inventory_checkin'              => array('label' => __('Registrar devolución', 'aura-suite'), 'code' => 'checkin'),
                    'aura_inventory_loan_edit'            => array('label' => __('Editar préstamos', 'aura-suite'), 'code' => 'loan_edit'),
                    'aura_inventory_loan_delete'          => array('label' => __('Eliminar préstamos', 'aura-suite'), 'code' => 'loan_delete'),
                    'aura_inventory_maintenance_create'   => array('label' => __('Registrar mantenimiento', 'aura-suite'), 'code' => 'maint_create'),
                    'aura_inventory_maintenance_edit'     => array('label' => __('Editar mantenimientos', 'aura-suite'), 'code' => 'maint_edit'),
                    'aura_inventory_maintenance_delete'   => array('label' => __('Eliminar mantenimientos', 'aura-suite'), 'code' => 'maint_delete'),
                    'aura_inventory_maintenance_schedule' => array('label' => __('Configurar calendario mantenimiento', 'aura-suite'), 'code' => 'maint_schedule', 'star' => true),
                    'aura_inventory_maintenance_view'     => array('label' => __('Ver historial mantenimientos', 'aura-suite'), 'code' => 'maint_view'),
                    'aura_inventory_maintenance_alerts'   => array('label' => __('Recibir alertas de mantenimiento', 'aura-suite'), 'code' => 'maint_alerts'),
                    'aura_inventory_maintenance_external' => array('label' => __('Registrar talleres externos', 'aura-suite'), 'code' => 'maint_external'),
                    'aura_inventory_stock_min'            => array('label' => __('Configurar stock mínimo', 'aura-suite'), 'code' => 'stock_min'),
                    'aura_inventory_reports'              => array('label' => __('Ver reportes de inventario', 'aura-suite'), 'code' => 'reports'),
                    'aura_inventory_categories'           => array('label' => __('Gestionar categorías', 'aura-suite'), 'code' => 'categories', 'star' => true),
                    'aura_inventory_cost_tracking'        => array('label' => __('Ver costos por equipo', 'aura-suite'), 'code' => 'cost_tracking'),
                    'aura_inventory_lifecycle'            => array('label' => __('Ver vida útil / depreciación', 'aura-suite'), 'code' => 'lifecycle'),
                ),
            ),
            // 3 — Estudiantes e Inscripciones
            array(
                'module'       => 'students',
                'icon'         => '🎓',
                'title'        => __('MÓDULO: ESTUDIANTES E INSCRIPCIONES', 'aura-suite'),
                'capabilities' => array(
                    'aura_students_create'              => array('label' => __('Crear/registrar estudiantes', 'aura-suite'), 'code' => 'create'),
                    'aura_students_edit'                => array('label' => __('Editar estudiantes', 'aura-suite'), 'code' => 'edit'),
                    'aura_students_delete'              => array('label' => __('Eliminar estudiantes', 'aura-suite'), 'code' => 'delete', 'star' => true),
                    'aura_students_view_all'            => array('label' => __('Ver todos los estudiantes', 'aura-suite'), 'code' => 'view_all'),
                    'aura_students_view_own'            => array('label' => __('Ver solo información propia', 'aura-suite'), 'code' => 'view_own'),
                    'aura_students_approve'             => array('label' => __('Aprobar/rechazar solicitudes', 'aura-suite'), 'code' => 'approve', 'star' => true),
                    'aura_students_enrollments_manage'  => array('label' => __('Gestionar inscripciones', 'aura-suite'), 'code' => 'enrollments_manage'),
                    'aura_students_scholarships_view'   => array('label' => __('Ver becas asignadas', 'aura-suite'), 'code' => 'scholarships_view'),
                    'aura_students_scholarships_assign' => array('label' => __('Asignar/modificar becas', 'aura-suite'), 'code' => 'scholarships_assign', 'star' => true),
                    'aura_students_payments_register'   => array('label' => __('Registrar pagos', 'aura-suite'), 'code' => 'payments_register'),
                    'aura_students_payments_view_all'   => array('label' => __('Ver pagos de todos', 'aura-suite'), 'code' => 'payments_view_all'),
                    'aura_students_payments_view_own'   => array('label' => __('Ver solo pagos propios', 'aura-suite'), 'code' => 'payments_view_own'),
                    'aura_students_quotas_config'       => array('label' => __('Configurar esquemas de cuotas', 'aura-suite'), 'code' => 'quotas_config', 'star' => true),
                    'aura_students_status_view'         => array('label' => __('Ver estado paz y salvo', 'aura-suite'), 'code' => 'status_view'),
                    'aura_students_courses_manage'      => array('label' => __('Gestionar cursos/programas', 'aura-suite'), 'code' => 'courses_manage'),
                    'aura_students_reports'             => array('label' => __('Ver reportes', 'aura-suite'), 'code' => 'reports'),
                    'aura_students_settings'            => array('label' => __('Configurar el módulo', 'aura-suite'), 'code' => 'settings', 'star' => true),
                ),
            ),
            // 4 — Certificados
            array(
                'module'       => 'certificates',
                'icon'         => '🏅',
                'title'        => __('MÓDULO: CERTIFICADOS', 'aura-suite'),
                'capabilities' => array(
                    'aura_cert_template_view'     => array('label' => __('Ver plantillas', 'aura-suite'), 'code' => 'template_view'),
                    'aura_cert_template_create'   => array('label' => __('Crear plantillas', 'aura-suite'), 'code' => 'template_create'),
                    'aura_cert_template_edit'     => array('label' => __('Editar plantillas', 'aura-suite'), 'code' => 'template_edit'),
                    'aura_cert_template_delete'   => array('label' => __('Eliminar plantillas', 'aura-suite'), 'code' => 'template_delete', 'star' => true),
                    'aura_cert_issue'             => array('label' => __('Emitir certificados', 'aura-suite'), 'code' => 'issue', 'star' => true),
                    'aura_cert_revoke'            => array('label' => __('Revocar certificados', 'aura-suite'), 'code' => 'revoke', 'star' => true),
                    'aura_cert_view_all'          => array('label' => __('Ver todos los emitidos', 'aura-suite'), 'code' => 'view_all'),
                    'aura_cert_download_any'      => array('label' => __('Descargar PDF de cualquier estudiante', 'aura-suite'), 'code' => 'download_any'),
                    'aura_cert_download_own'      => array('label' => __('Descargar propio certificado', 'aura-suite'), 'code' => 'download_own'),
                    'aura_cert_signatures_manage' => array('label' => __('Gestionar firmantes', 'aura-suite'), 'code' => 'signatures_manage', 'star' => true),
                    'aura_cert_reports'           => array('label' => __('Ver reportes', 'aura-suite'), 'code' => 'reports'),
                    'aura_cert_settings'          => array('label' => __('Configurar el módulo', 'aura-suite'), 'code' => 'settings', 'star' => true),
                ),
            ),
            // 5 — Formularios
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
                    'aura_forms_assign'             => array('label' => __('Asignar encuestas', 'aura-suite'), 'code' => 'assign'),
                    'aura_forms_enrollment_review'  => array('label' => __('Revisar postulantes', 'aura-suite'), 'code' => 'enrollment_review', 'star' => true),
                    'aura_forms_settings'           => array('label' => __('Configuración del módulo', 'aura-suite'), 'code' => 'settings', 'star' => true),
                    'aura_forms_reports'            => array('label' => __('Ver reportes', 'aura-suite'), 'code' => 'reports'),
                ),
            ),
            // 6 — Vehículos
            array(
                'module'       => 'vehicles',
                'icon'         => '🚗',
                'title'        => __('MÓDULO: VEHÍCULOS', 'aura-suite'),
                'capabilities' => array(
                    'aura_vehicles_create'         => array('label' => __('Crear vehículos', 'aura-suite'), 'code' => 'create'),
                    'aura_vehicles_edit'           => array('label' => __('Editar vehículos', 'aura-suite'), 'code' => 'edit'),
                    'aura_vehicles_delete'         => array('label' => __('Eliminar vehículos', 'aura-suite'), 'code' => 'delete'),
                    'aura_vehicles_exits_create'   => array('label' => __('Registrar salidas', 'aura-suite'), 'code' => 'exits_create'),
                    'aura_vehicles_exits_edit_own' => array('label' => __('Editar propias salidas', 'aura-suite'), 'code' => 'exits_edit_own'),
                    'aura_vehicles_exits_edit_all' => array('label' => __('Editar todas las salidas', 'aura-suite'), 'code' => 'exits_edit_all'),
                    'aura_vehicles_km_update'      => array('label' => __('Actualizar kilometraje', 'aura-suite'), 'code' => 'km_update'),
                    'aura_vehicles_view_all'       => array('label' => __('Ver todos los vehículos', 'aura-suite'), 'code' => 'view_all'),
                    'aura_vehicles_reports'        => array('label' => __('Ver reportes', 'aura-suite'), 'code' => 'reports'),
                    'aura_vehicles_alerts'         => array('label' => __('Recibir alertas', 'aura-suite'), 'code' => 'alerts'),
                    'aura_vehicles_audit'          => array('label' => __('Ver auditoría', 'aura-suite'), 'code' => 'audit', 'star' => true),
                    'aura_vehicles_settings'       => array('label' => __('Configurar módulo', 'aura-suite'), 'code' => 'settings', 'star' => true),
                ),
            ),
            // 7 — Biblioteca
            array(
                'module'       => 'library',
                'icon'         => '📚',
                'title'        => __('MÓDULO: BIBLIOTECA', 'aura-suite'),
                'capabilities' => array(
                    'aura_library_access'         => array('label' => __('Acceder al módulo', 'aura-suite'), 'code' => 'access'),
                    'aura_library_create'         => array('label' => __('Agregar libros al catálogo', 'aura-suite'), 'code' => 'create'),
                    'aura_library_edit'           => array('label' => __('Editar libros', 'aura-suite'), 'code' => 'edit'),
                    'aura_library_delete'         => array('label' => __('Eliminar libros', 'aura-suite'), 'code' => 'delete', 'star' => true),
                    'aura_library_view_catalog'   => array('label' => __('Ver catálogo', 'aura-suite'), 'code' => 'view_catalog'),
                    'aura_library_loan_create'    => array('label' => __('Registrar préstamo', 'aura-suite'), 'code' => 'loan_create'),
                    'aura_library_loan_return'    => array('label' => __('Registrar devolución', 'aura-suite'), 'code' => 'loan_return'),
                    'aura_library_loan_extend'    => array('label' => __('Extender plazo de préstamo', 'aura-suite'), 'code' => 'loan_extend'),
                    'aura_library_loan_edit'      => array('label' => __('Editar préstamo', 'aura-suite'), 'code' => 'loan_edit', 'star' => true),
                    'aura_library_loan_delete'    => array('label' => __('Cancelar/eliminar préstamo', 'aura-suite'), 'code' => 'loan_delete', 'star' => true),
                    'aura_library_view_loans_own' => array('label' => __('Ver mis préstamos', 'aura-suite'), 'code' => 'view_loans_own'),
                    'aura_library_view_loans_all' => array('label' => __('Ver todos los préstamos', 'aura-suite'), 'code' => 'view_loans_all'),
                    'aura_library_reports'        => array('label' => __('Ver reportes', 'aura-suite'), 'code' => 'reports'),
                    'aura_library_alerts'         => array('label' => __('Recibir alertas de vencimiento', 'aura-suite'), 'code' => 'alerts'),
                    'aura_library_settings'       => array('label' => __('Configurar módulo', 'aura-suite'), 'code' => 'settings', 'star' => true),
                    'aura_library_audit'          => array('label' => __('Ver auditoría', 'aura-suite'), 'code' => 'audit', 'star' => true),
                ),
            ),
            // 8 — Electricidad
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
            // 9 — Áreas y Programas
            array(
                'module'       => 'areas',
                'icon'         => '🏛️',
                'title'        => __('MÓDULO: ÁREAS Y PROGRAMAS', 'aura-suite'),
                'capabilities' => array(
                    'aura_areas_manage'            => array('label' => __('Gestionar áreas/programas', 'aura-suite'), 'code' => 'manage', 'star' => true),
                    'aura_areas_types_manage'      => array('label' => __('Gestionar tipos de área', 'aura-suite'), 'code' => 'types_manage'),
                    'aura_areas_view_all'          => array('label' => __('Ver todas las áreas', 'aura-suite'), 'code' => 'view_all'),
                    'aura_areas_view_own'          => array('label' => __('Ver solo área asignada', 'aura-suite'), 'code' => 'view_own'),
                    'aura_areas_budget_manage'     => array('label' => __('Gestionar presupuesto de área', 'aura-suite'), 'code' => 'budget_manage'),
                    'aura_areas_budget_view'       => array('label' => __('Ver presupuesto de área', 'aura-suite'), 'code' => 'budget_view'),
                    'aura_areas_assign_user'       => array('label' => __('Asignar responsable a área', 'aura-suite'), 'code' => 'assign_user'),
                    'aura_areas_forms_manage'      => array('label' => __('Crear formularios del área', 'aura-suite'), 'code' => 'forms_manage'),
                    'aura_areas_enrollment_manage' => array('label' => __('Gestionar inscripciones del área', 'aura-suite'), 'code' => 'enrollment_manage'),
                ),
            ),
            // 10 — Administración
            array(
                'module'       => 'admin',
                'icon'         => '⚙️',
                'title'        => __('MÓDULO: ADMINISTRACIÓN', 'aura-suite'),
                'capabilities' => array(
                    'aura_admin_users_create'       => array('label' => __('Crear nuevos usuarios (suscriptor)', 'aura-suite'), 'code' => 'users_create', 'star' => true),
                    'aura_admin_users_manage'       => array('label' => __('Gestionar usuarios existentes', 'aura-suite'), 'code' => 'users_manage'),
                    'aura_admin_permissions_assign' => array('label' => __('Asignar permisos a usuarios', 'aura-suite'), 'code' => 'permissions_assign', 'star' => true),
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
        // admin-ajax.php también ejecuta admin_init; no debe bloquear los flujos
        // frontend que usan wp_ajax_nopriv_* (QR, estudiantes, formularios, etc.).
        if ( wp_doing_ajax() ) {
            return;
        }

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
     * Redirigir usuarios Aura al dashboard de WordPress después del login.
     *
     * @param string           $redirect_to           URL de destino por defecto.
     * @param string           $requested_redirect_to  URL solicitada.
     * @param WP_User|WP_Error $user                  Usuario autenticado.
     * @return string
     */
    public static function redirect_aura_users_to_admin( $redirect_to, $requested_redirect_to, $user ) {
        if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
            return $redirect_to;
        }

        if ( $user->has_cap( 'administrator' ) || self::user_has_any_aura_capability_for_user( $user ) ) {
            return admin_url();
        }

        return $redirect_to;
    }

    /**
     * Versión para el filtro de WooCommerce `woocommerce_login_redirect`.
     *
     * @param string  $redirect URL de destino.
     * @param WP_User $user     Usuario autenticado.
     * @return string
     */
    public static function redirect_aura_users_to_admin_wc( $redirect, $user ) {
        if ( ! ( $user instanceof WP_User ) ) {
            return $redirect;
        }

        if ( $user->has_cap( 'administrator' ) || self::user_has_any_aura_capability_for_user( $user ) ) {
            return admin_url();
        }

        return $redirect;
    }

    /**
     * Evitar que WooCommerce bloquee el acceso al wp-admin para usuarios Aura.
     *
     * @param bool $prevent Si WooCommerce quiere bloquear acceso.
     * @return bool
     */
    public static function allow_aura_users_admin_access( $prevent ) {
        if ( ! $prevent ) {
            return false;
        }

        if ( self::user_has_any_aura_capability() ) {
            return false;
        }

        return $prevent;
    }

    /**
     * Si un usuario Aura aterriza en la página de cuenta de WooCommerce,
     * mandarlo al wp-admin para evitar la experiencia de "me envía a mi-cuenta".
     */
    public static function maybe_redirect_aura_users_from_account_page() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        if ( ! self::user_has_any_aura_capability() ) {
            return;
        }

        $is_my_account = false;

        if ( function_exists( 'is_account_page' ) && is_account_page() ) {
            $is_my_account = true;
        }

        if ( ! $is_my_account ) {
            $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
            $request_uri = strtolower( $request_uri );

            if ( false !== strpos( $request_uri, '/mi-cuenta/' ) || false !== strpos( $request_uri, '/my-account/' ) ) {
                $is_my_account = true;
            }
        }

        if ( $is_my_account ) {
            wp_safe_redirect( admin_url() );
            exit;
        }
    }

    /**
     * Verifica si un usuario concreto tiene alguna capability de Aura.
     *
     * @param WP_User $user Usuario a evaluar.
     * @return bool
     */
    public static function user_has_any_aura_capability_for_user( $user ) {
        if ( ! ( $user instanceof WP_User ) ) {
            return false;
        }

        $all_caps = self::get_all_capabilities();

        foreach ( $all_caps as $module => $caps ) {
            foreach ( $caps as $cap => $description ) {
                if ( $user->has_cap( $cap ) ) {
                    return true;
                }
            }
        }

        return false;
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

    /**
     * Conceder la capability nativa upload_files a usuarios que tienen permisos
     * de inventario o vehículos que implican gestión de imágenes.
     *
     * WordPress exige upload_files para cualquier subida vía async-upload.php
     * y para acceder al modal de la Biblioteca de Medios.
     *
     * @param bool[]   $allcaps Capabilities actuales del usuario.
     * @param string[] $caps    Capabilities requeridas en la comprobación.
     * @param array    $args    Argumentos adicionales (cap solicitada, user_id, ...).
     * @param WP_User  $user    Objeto del usuario.
     * @return bool[] Capabilities modificadas.
     */
    public static function grant_upload_cap_for_media_modules( $allcaps, $caps, $args, $user ) {
        // Si ya tiene la capability, no es necesario hacer nada
        if ( ! empty( $allcaps['upload_files'] ) ) {
            return $allcaps;
        }

        // Caps de módulos que requieren subida/gestión de imágenes
        $media_required_caps = [
            'aura_inventory_create',
            'aura_inventory_edit',
            'aura_vehicles_create',
            'aura_vehicles_edit',
        ];

        foreach ( $media_required_caps as $cap ) {
            if ( ! empty( $allcaps[ $cap ] ) ) {
                $allcaps['upload_files'] = true;
                break;
            }
        }

        return $allcaps;
    }

    /**
     * Permitir que usuarios con caps de módulos de imágenes vean todos los adjuntos
     * en la Biblioteca de Medios, no solo los suyos propios.
     *
     * WordPress restringe la vista a los propios uploads cuando el usuario no
     * tiene edit_posts; esta función elimina esa restricción para los módulos
     * de inventario y vehículos.
     *
     * @param array $query Argumentos de consulta WP_Query para la media library.
     * @return array Argumentos modificados.
     */
    public static function allow_view_all_media_for_modules( $query ) {
        $user = wp_get_current_user();
        if ( ! $user || ! $user->ID || $user->has_cap( 'administrator' ) ) {
            return $query;
        }

        $media_required_caps = [
            'aura_inventory_create',
            'aura_inventory_edit',
            'aura_vehicles_create',
            'aura_vehicles_edit',
        ];

        foreach ( $media_required_caps as $cap ) {
            if ( $user->has_cap( $cap ) ) {
                unset( $query['author'] );
                break;
            }
        }

        return $query;
    }

    /**
     * Devuelve solo los usuarios que tienen al menos una capability de Aura Suite.
     * Excluye suscriptores puros (WooCommerce, LMS, etc.) que no pertenecen a ningún módulo.
     *
     * @param array  $extra_args    Argumentos adicionales para get_users().
     * @param string $module_prefix Prefijo de módulo para filtrar más específicamente,
     *                              p.ej. 'aura_finance_'. Vacío = cualquier cap Aura.
     * @return WP_User[]
     */
    public static function get_aura_users( array $extra_args = [], string $module_prefix = '' ): array {
        global $wpdb;
        $like_value = '"' . ( $module_prefix ?: 'aura_' );

        $defaults = [
            'orderby'    => 'display_name',
            'meta_query' => [
                [
                    'key'     => $wpdb->prefix . 'capabilities',
                    'value'   => $like_value,
                    'compare' => 'LIKE',
                ],
            ],
        ];

        // Fusionar: meta_query del caller tiene prioridad; el resto se combina
        $args = array_merge( $defaults, $extra_args );
        if ( isset( $extra_args['meta_query'] ) ) {
            $args['meta_query'] = array_merge( $defaults['meta_query'], $extra_args['meta_query'] );
        }

        return get_users( $args );
    }
}
