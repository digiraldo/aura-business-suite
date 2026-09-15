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

        // Conceder manage_dashboard y read a usuarios Aura para que puedan
        // acceder a wp-admin (WP lo exige aunque el usuario sea suscriptor).
        add_filter('user_has_cap', array(__CLASS__, 'grant_admin_dashboard_cap'), 5, 4);

        // Permitir ver todos los medios del sistema (no solo los propios) a esos mismos usuarios
        add_filter('ajax_query_attachments_args', array(__CLASS__, 'allow_view_all_media_for_modules'));

        // Hook AJAX para aplicar plantillas de perfiles predefinidos
        add_action('wp_ajax_aura_apply_profile_template', array(__CLASS__, 'ajax_apply_profile_template'));
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
                'aura_finance_bulk_edit'       => __('Edición masiva de transacciones (cambiar en lote área, método de pago, vinculado, creador o categoría)', 'aura-suite'),
                // Fase 6: Vinculación de Usuarios
                'aura_finance_link_user'           => __('Vincular usuario del sistema a una transacción', 'aura-suite'),
                'aura_finance_user_ledger'         => __('Ver libro mayor agrupado por usuario', 'aura-suite'),
                'aura_finance_view_user_summary'   => __('Ver propio dashboard financiero personal', 'aura-suite'),
                'aura_finance_view_others_summary' => __('Ver dashboard financiero de otros usuarios', 'aura-suite'),
                // Herramientas Avanzadas, Auditoría e Integraciones Contables
                'aura_finance_integrations'        => __('Gestionar integraciones contables (QuickBooks, SAP, Contabilidad MX, Excel XML)', 'aura-suite'),
                'aura_finance_audit'               => __('Ver y auditar logs de trazabilidad financiera', 'aura-suite'),
                'aura_finance_import'              => __('Importar transacciones desde archivos CSV / Excel', 'aura-suite'),
                'aura_finance_budgets'             => __('Gestionar presupuestos y techos de gasto por área', 'aura-suite'),
                'aura_finance_tags'                => __('Gestionar y fusionar etiquetas de transacciones', 'aura-suite'),
                'aura_finance_accounts_manage'     => __('Gestionar cuentas bancarias, números de cuenta y cajas', 'aura-suite'),
                'aura_finance_petty_cash_approve'  => __('Aprobar / rechazar gastos de caja chica', 'aura-suite'),
                // Operaciones y Auditoría de Cambio de Divisas (Compra y Venta)
                'aura_finance_exchange_view'       => __('Ver historial y auditoría de cambio de divisas', 'aura-suite'),
                'aura_finance_exchange_create'     => __('Realizar y registrar operaciones de cambio de divisas', 'aura-suite'),
                'aura_finance_exchange_edit'       => __('Editar notas y referencias de operaciones de cambio de divisas', 'aura-suite'),
                'aura_finance_exchange_delete'     => __('Anular y revertir operaciones de cambio de divisas', 'aura-suite'),
            ),
            'vehicles' => array(
                'aura_vehicles_create'          => __('Crear/registrar vehículos', 'aura-suite'),
                'aura_vehicles_edit'            => __('Editar vehículos', 'aura-suite'),
                'aura_vehicles_delete'          => __('Eliminar vehículos', 'aura-suite'),
                'aura_vehicles_exits_create'    => __('Registrar salidas', 'aura-suite'),
                'aura_vehicles_exits_edit_own'  => __('Editar propias salidas', 'aura-suite'),
                'aura_vehicles_exits_edit_all'  => __('Editar todas las salidas', 'aura-suite'),
                'aura_vehicles_exits_delete_own'=> __('Eliminar propias salidas', 'aura-suite'),
                'aura_vehicles_exits_delete_all'=> __('Eliminar todas las salidas', 'aura-suite'),
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
                'aura_admin_gdrive_config'      => __('Configurar y probar conexión con Google Drive', 'aura-suite'),
                'aura_admin_notifications_view' => __('Ver centro de notificaciones y alertas globales', 'aura-suite'),
                'aura_admin_modules_enable'     => __('Activar/desactivar módulos', 'aura-suite'),
                'aura_admin_backup'             => __('Gestionar backups', 'aura-suite'),
                'aura_admin_logs'               => __('Ver logs de auditoría', 'aura-suite'),
            ),
            // Directorio Maestro de Terceros y Empresas
            'third_parties' => array(
                'aura_third_parties_view'           => __('Ver directorio de terceros y empresas', 'aura-suite'),
                'aura_third_parties_create'         => __('Registrar nuevos terceros y empresas', 'aura-suite'),
                'aura_third_parties_edit'           => __('Editar terceros y empresas', 'aura-suite'),
                'aura_third_parties_delete'         => __('Eliminar/desactivar terceros y empresas', 'aura-suite'),
                'aura_third_parties_create_wp_user' => __('Crear usuarios de WordPress (Suscriptor) desde Terceros', 'aura-suite'),
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
            // ═══════════════════════════════════════════════════════════════
            // GRUPO 1: 👑 ADMINISTRACIÓN Y GOBIERNO GLOBAL
            // ═══════════════════════════════════════════════════════════════
            array(
                'group'        => 'admin',
                'module'       => 'admin',
                'icon'         => '⚙️',
                'title'        => __('MÓDULO: ADMINISTRACIÓN Y SISTEMA', 'aura-suite'),
                'capabilities' => array(
                    'aura_admin_users_manage'       => array('label' => __('Gestionar usuarios existentes', 'aura-suite'), 'code' => 'users_manage'),
                    'aura_admin_users_create'       => array('label' => __('Crear nuevos usuarios (suscriptor)', 'aura-suite'), 'code' => 'users_create', 'star' => true),
                    'aura_admin_permissions_assign' => array('label' => __('Asignar permisos a usuarios', 'aura-suite'), 'code' => 'permissions_assign', 'star' => true),
                    'aura_admin_settings'           => array('label' => __('Configuración general del sistema', 'aura-suite'), 'code' => 'settings'),
                    'aura_admin_gdrive_config'      => array('label' => __('Configurar y probar Google Drive', 'aura-suite'), 'code' => 'gdrive_config', 'star' => true),
                    'aura_admin_notifications_view' => array('label' => __('Centro de notificaciones y alertas', 'aura-suite'), 'code' => 'notifications_view'),
                    'aura_admin_modules_enable'     => array('label' => __('Activar / desactivar módulos', 'aura-suite'), 'code' => 'modules_enable', 'star' => true),
                    'aura_admin_backup'             => array('label' => __('Gestionar copias de seguridad (backups)', 'aura-suite'), 'code' => 'backup', 'star' => true),
                    'aura_admin_logs'               => array('label' => __('Ver logs de auditoría global', 'aura-suite'), 'code' => 'logs'),
                ),
            ),
            array(
                'group'        => 'admin',
                'module'       => 'areas',
                'icon'         => '🏛️',
                'title'        => __('MÓDULO: ÁREAS Y PROGRAMAS', 'aura-suite'),
                'capabilities' => array(
                    'aura_areas_view_own'          => array('label' => __('Ver solo área asignada como responsable', 'aura-suite'), 'code' => 'view_own'),
                    'aura_areas_view_all'          => array('label' => __('Ver todas las áreas y programas', 'aura-suite'), 'code' => 'view_all'),
                    'aura_areas_budget_view'       => array('label' => __('Ver presupuesto del área', 'aura-suite'), 'code' => 'budget_view'),
                    'aura_areas_forms_manage'      => array('label' => __('Crear formularios propios del área', 'aura-suite'), 'code' => 'forms_manage'),
                    'aura_areas_enrollment_manage' => array('label' => __('Gestionar inscripciones del área', 'aura-suite'), 'code' => 'enrollment_manage'),
                    'aura_areas_assign_user'       => array('label' => __('Asignar responsable / director a área', 'aura-suite'), 'code' => 'assign_user'),
                    'aura_areas_budget_manage'     => array('label' => __('Gestionar presupuesto de área', 'aura-suite'), 'code' => 'budget_manage'),
                    'aura_areas_types_manage'      => array('label' => __('Gestionar tipos y sedes de área', 'aura-suite'), 'code' => 'types_manage'),
                    'aura_areas_manage'            => array('label' => __('Administración total de áreas y programas', 'aura-suite'), 'code' => 'manage', 'star' => true),
                ),
            ),
            array(
                'group'        => 'admin',
                'module'       => 'third_parties',
                'icon'         => '👥',
                'title'        => __('MÓDULO: TERCEROS Y EMPRESAS', 'aura-suite'),
                'capabilities' => array(
                    'aura_third_parties_view'   => array('label' => __('Ver directorio de terceros y empresas', 'aura-suite'), 'code' => 'view_all'),
                    'aura_third_parties_create' => array('label' => __('Crear/registrar terceros y empresas', 'aura-suite'), 'code' => 'create'),
                    'aura_third_parties_edit'   => array('label' => __('Editar terceros y empresas', 'aura-suite'), 'code' => 'edit'),
                    'aura_third_parties_delete'         => array('label' => __('Eliminar/desactivar terceros y empresas', 'aura-suite'), 'code' => 'delete', 'star' => true),
                    'aura_third_parties_create_wp_user' => array('label' => __('Crear usuarios de WordPress (Suscriptor) desde Terceros', 'aura-suite'), 'code' => 'create_wp_user', 'star' => true),
                ),
            ),

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 2: 💰 GESTIÓN FINANCIERA Y CONTABLE
            // ═══════════════════════════════════════════════════════════════
            array(
                'group'        => 'finance',
                'module'       => 'finance',
                'icon'         => '💰',
                'title'        => __('MÓDULO: FINANZAS Y TESORERÍA', 'aura-suite'),
                'capabilities' => array(
                    'aura_finance_view_own'            => array('label' => __('Ver solo transacciones propias', 'aura-suite'), 'code' => 'view_own'),
                    'aura_finance_view_all'            => array('label' => __('Ver todas las transacciones', 'aura-suite'), 'code' => 'view_all'),
                    'aura_finance_view_user_summary'   => array('label' => __('Ver mi dashboard financiero personal', 'aura-suite'), 'code' => 'view_user_summary'),
                    'aura_finance_view_others_summary' => array('label' => __('Ver dashboard financiero de otros usuarios', 'aura-suite'), 'code' => 'view_others_summary', 'star' => true),
                    'aura_finance_charts'              => array('label' => __('Ver gráficos financieros', 'aura-suite'), 'code' => 'charts'),
                    'aura_finance_export'              => array('label' => __('Exportar reportes contables', 'aura-suite'), 'code' => 'export'),
                    'aura_finance_user_ledger'         => array('label' => __('Ver libro mayor por usuario', 'aura-suite'), 'code' => 'user_ledger'),
                    'aura_finance_create'              => array('label' => __('Registrar nuevas transacciones', 'aura-suite'), 'code' => 'create'),
                    'aura_finance_link_user'           => array('label' => __('Vincular usuario a transacción', 'aura-suite'), 'code' => 'link_user'),
                    'aura_finance_edit_own'            => array('label' => __('Editar transacciones propias', 'aura-suite'), 'code' => 'edit_own'),
                    'aura_finance_edit_all'            => array('label' => __('Editar todas las transacciones', 'aura-suite'), 'code' => 'edit_all'),
                    'aura_finance_bulk_edit'           => array('label' => __('Edición masiva de transacciones (en lote)', 'aura-suite'), 'code' => 'bulk_edit'),
                    'aura_finance_approve'             => array('label' => __('Aprobar / rechazar gastos', 'aura-suite'), 'code' => 'approve', 'star' => true),
                    'aura_finance_category_manage'     => array('label' => __('Gestionar categorías de gastos/ingresos', 'aura-suite'), 'code' => 'category_manage'),
                    'aura_finance_integrations'        => array('label' => __('Integraciones contables (QuickBooks, SAP, MX, Excel)', 'aura-suite'), 'code' => 'integrations', 'star' => true),
                    'aura_finance_audit'               => array('label' => __('Ver trazabilidad y auditoría contable', 'aura-suite'), 'code' => 'audit', 'star' => true),
                    'aura_finance_import'              => array('label' => __('Importar transacciones desde CSV / Excel', 'aura-suite'), 'code' => 'import', 'star' => true),
                    'aura_finance_budgets'             => array('label' => __('Gestionar presupuestos y techos de gasto', 'aura-suite'), 'code' => 'budgets'),
                    'aura_finance_tags'                => array('label' => __('Gestionar etiquetas clasificatorias', 'aura-suite'), 'code' => 'tags'),
                    'aura_finance_accounts_manage'     => array('label' => __('Gestionar cuentas bancarias y cajas', 'aura-suite'), 'code' => 'accounts_manage', 'star' => true),
                    'aura_finance_petty_cash_approve'  => array('label' => __('Aprobar / rechazar gastos de caja chica', 'aura-suite'), 'code' => 'petty_cash_approve', 'star' => true),
                    'aura_finance_delete_own'          => array('label' => __('Eliminar transacciones propias', 'aura-suite'), 'code' => 'delete_own'),
                    'aura_finance_delete_all'          => array('label' => __('Eliminar cualquier transacción', 'aura-suite'), 'code' => 'delete_all', 'star' => true),
                ),
            ),

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 3: 🎓 ACADÉMICO, INSCRIPCIONES Y FORMULARIOS
            // ═══════════════════════════════════════════════════════════════
            array(
                'group'        => 'academic',
                'module'       => 'students',
                'icon'         => '🎓',
                'title'        => __('MÓDULO: ESTUDIANTES E INSCRIPCIONES', 'aura-suite'),
                'capabilities' => array(
                    'aura_students_view_own'            => array('label' => __('Ver solo información propia (estudiante)', 'aura-suite'), 'code' => 'view_own'),
                    'aura_students_view_all'            => array('label' => __('Ver todos los estudiantes', 'aura-suite'), 'code' => 'view_all'),
                    'aura_students_status_view'         => array('label' => __('Ver estado y paz y salvo', 'aura-suite'), 'code' => 'status_view'),
                    'aura_students_scholarships_view'   => array('label' => __('Ver becas asignadas', 'aura-suite'), 'code' => 'scholarships_view'),
                    'aura_students_payments_view_own'   => array('label' => __('Ver solo pagos propios', 'aura-suite'), 'code' => 'payments_view_own'),
                    'aura_students_payments_view_all'   => array('label' => __('Ver pagos de todos los estudiantes', 'aura-suite'), 'code' => 'payments_view_all'),
                    'aura_students_reports'             => array('label' => __('Ver reportes de inscripciones y pagos', 'aura-suite'), 'code' => 'reports'),
                    'aura_students_create'              => array('label' => __('Registrar nuevos estudiantes', 'aura-suite'), 'code' => 'create'),
                    'aura_students_payments_register'   => array('label' => __('Registrar pagos de estudiantes', 'aura-suite'), 'code' => 'payments_register'),
                    'aura_students_enrollments_manage'  => array('label' => __('Gestionar inscripciones a cursos', 'aura-suite'), 'code' => 'enrollments_manage'),
                    'aura_students_courses_manage'      => array('label' => __('Gestionar cursos y programas', 'aura-suite'), 'code' => 'courses_manage'),
                    'aura_students_edit'                => array('label' => __('Editar información de estudiantes', 'aura-suite'), 'code' => 'edit'),
                    'aura_students_approve'             => array('label' => __('Aprobar / rechazar solicitudes', 'aura-suite'), 'code' => 'approve', 'star' => true),
                    'aura_students_scholarships_assign' => array('label' => __('Asignar / modificar becas', 'aura-suite'), 'code' => 'scholarships_assign', 'star' => true),
                    'aura_students_quotas_config'       => array('label' => __('Configurar esquemas de cuotas', 'aura-suite'), 'code' => 'quotas_config', 'star' => true),
                    'aura_students_settings'            => array('label' => __('Configurar el módulo de estudiantes', 'aura-suite'), 'code' => 'settings', 'star' => true),
                    'aura_students_delete'              => array('label' => __('Eliminar estudiantes', 'aura-suite'), 'code' => 'delete', 'star' => true),
                ),
            ),
            array(
                'group'        => 'academic',
                'module'       => 'certificates',
                'icon'         => '🏅',
                'title'        => __('MÓDULO: CERTIFICADOS Y DIPLOMAS', 'aura-suite'),
                'capabilities' => array(
                    'aura_cert_view_all'          => array('label' => __('Ver listado de certificados emitidos', 'aura-suite'), 'code' => 'view_all'),
                    'aura_cert_download_own'      => array('label' => __('Descargar certificado propio', 'aura-suite'), 'code' => 'download_own'),
                    'aura_cert_download_any'      => array('label' => __('Descargar PDF de cualquier estudiante', 'aura-suite'), 'code' => 'download_any'),
                    'aura_cert_template_view'     => array('label' => __('Ver plantillas de diseño', 'aura-suite'), 'code' => 'template_view'),
                    'aura_cert_reports'           => array('label' => __('Ver reportes de certificados', 'aura-suite'), 'code' => 'reports'),
                    'aura_cert_template_create'   => array('label' => __('Crear nuevas plantillas', 'aura-suite'), 'code' => 'template_create'),
                    'aura_cert_template_edit'     => array('label' => __('Editar plantillas de certificados', 'aura-suite'), 'code' => 'template_edit'),
                    'aura_cert_issue'             => array('label' => __('Emitir certificados a estudiantes', 'aura-suite'), 'code' => 'issue', 'star' => true),
                    'aura_cert_signatures_manage' => array('label' => __('Gestionar firmantes y sellos', 'aura-suite'), 'code' => 'signatures_manage', 'star' => true),
                    'aura_cert_settings'          => array('label' => __('Configuración del módulo de certificados', 'aura-suite'), 'code' => 'settings', 'star' => true),
                    'aura_cert_revoke'            => array('label' => __('Revocar certificados emitidos', 'aura-suite'), 'code' => 'revoke', 'star' => true),
                    'aura_cert_template_delete'   => array('label' => __('Eliminar plantillas', 'aura-suite'), 'code' => 'template_delete', 'star' => true),
                ),
            ),
            array(
                'group'        => 'academic',
                'module'       => 'forms',
                'icon'         => '📝',
                'title'        => __('MÓDULO: FORMULARIOS Y POSTULACIONES', 'aura-suite'),
                'capabilities' => array(
                    'aura_forms_submit'             => array('label' => __('Llenar formularios habilitados', 'aura-suite'), 'code' => 'submit'),
                    'aura_forms_view_responses_all' => array('label' => __('Ver todas las respuestas recibidas', 'aura-suite'), 'code' => 'view_all'),
                    'aura_forms_analytics'          => array('label' => __('Ver análisis y métricas de formularios', 'aura-suite'), 'code' => 'analytics'),
                    'aura_forms_export'             => array('label' => __('Exportar respuestas (Excel / CSV)', 'aura-suite'), 'code' => 'export'),
                    'aura_forms_reports'            => array('label' => __('Ver reportes de formularios', 'aura-suite'), 'code' => 'reports'),
                    'aura_forms_create'             => array('label' => __('Crear nuevos formularios', 'aura-suite'), 'code' => 'create'),
                    'aura_forms_edit'               => array('label' => __('Editar formularios existentes', 'aura-suite'), 'code' => 'edit'),
                    'aura_forms_assign'             => array('label' => __('Asignar encuestas a estudiantes/usuarios', 'aura-suite'), 'code' => 'assign'),
                    'aura_forms_enrollment_review'  => array('label' => __('Revisar postulantes de admisión', 'aura-suite'), 'code' => 'enrollment_review', 'star' => true),
                    'aura_forms_settings'           => array('label' => __('Configuración general del módulo', 'aura-suite'), 'code' => 'settings', 'star' => true),
                    'aura_forms_delete'             => array('label' => __('Eliminar formularios', 'aura-suite'), 'code' => 'delete', 'star' => true),
                ),
            ),

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 4: 📦 OPERACIONES, LOGÍSTICA Y RECURSOS
            // ═══════════════════════════════════════════════════════════════
            array(
                'group'        => 'operations',
                'module'       => 'inventory',
                'icon'         => '📦',
                'title'        => __('MÓDULO: INVENTARIO Y MANTENIMIENTOS', 'aura-suite'),
                'capabilities' => array(
                    'aura_inventory_view_all'             => array('label' => __('Ver todo el catálogo de inventario', 'aura-suite'), 'code' => 'view_all'),
                    'aura_inventory_maintenance_view'     => array('label' => __('Ver historial de mantenimientos', 'aura-suite'), 'code' => 'maint_view'),
                    'aura_inventory_cost_tracking'        => array('label' => __('Ver costos de mantenimiento por equipo', 'aura-suite'), 'code' => 'cost_tracking'),
                    'aura_inventory_lifecycle'            => array('label' => __('Ver vida útil y depreciación', 'aura-suite'), 'code' => 'lifecycle'),
                    'aura_inventory_reports'              => array('label' => __('Ver reportes de disponibilidad y uso', 'aura-suite'), 'code' => 'reports'),
                    'aura_inventory_maintenance_alerts'   => array('label' => __('Recibir alertas de mantenimiento', 'aura-suite'), 'code' => 'maint_alerts'),
                    'aura_inventory_create'               => array('label' => __('Registrar nuevos equipos y herramientas', 'aura-suite'), 'code' => 'create'),
                    'aura_inventory_checkout'             => array('label' => __('Registrar préstamo / salida de equipos', 'aura-suite'), 'code' => 'checkout'),
                    'aura_inventory_checkin'              => array('label' => __('Registrar devolución de equipos', 'aura-suite'), 'code' => 'checkin'),
                    'aura_inventory_loan_edit'            => array('label' => __('Editar registros de préstamos', 'aura-suite'), 'code' => 'loan_edit'),
                    'aura_inventory_maintenance_create'   => array('label' => __('Registrar mantenimiento realizado', 'aura-suite'), 'code' => 'maint_create'),
                    'aura_inventory_maintenance_edit'     => array('label' => __('Editar registros de mantenimiento', 'aura-suite'), 'code' => 'maint_edit'),
                    'aura_inventory_maintenance_external' => array('label' => __('Registrar servicios en talleres externos', 'aura-suite'), 'code' => 'maint_external'),
                    'aura_inventory_edit'                 => array('label' => __('Editar datos y fichas de equipos', 'aura-suite'), 'code' => 'edit'),
                    'aura_inventory_stock_min'            => array('label' => __('Configurar stock mínimo y alertas', 'aura-suite'), 'code' => 'stock_min'),
                    'aura_inventory_maintenance_schedule' => array('label' => __('Configurar calendarios de mantenimiento', 'aura-suite'), 'code' => 'maint_schedule', 'star' => true),
                    'aura_inventory_categories'           => array('label' => __('Gestionar categorías de inventario', 'aura-suite'), 'code' => 'categories', 'star' => true),
                    'aura_inventory_loan_delete'          => array('label' => __('Cancelar / eliminar préstamos', 'aura-suite'), 'code' => 'loan_delete'),
                    'aura_inventory_maintenance_delete'   => array('label' => __('Eliminar registros de mantenimiento', 'aura-suite'), 'code' => 'maint_delete'),
                    'aura_inventory_delete'               => array('label' => __('Eliminar equipos del inventario', 'aura-suite'), 'code' => 'delete', 'star' => true),
                ),
            ),
            array(
                'group'        => 'operations',
                'module'       => 'vehicles',
                'icon'         => '🚗',
                'title'        => __('MÓDULO: VEHÍCULOS Y FLOTA', 'aura-suite'),
                'capabilities' => array(
                    'aura_vehicles_view_all'         => array('label' => __('Ver todos los vehículos', 'aura-suite'), 'code' => 'view_all'),
                    'aura_vehicles_reports'          => array('label' => __('Ver reportes y estadísticas de flota', 'aura-suite'), 'code' => 'reports'),
                    'aura_vehicles_alerts'           => array('label' => __('Recibir alertas de mantenimiento', 'aura-suite'), 'code' => 'alerts'),
                    'aura_vehicles_create'           => array('label' => __('Crear/registrar vehículos', 'aura-suite'), 'code' => 'create'),
                    'aura_vehicles_exits_create'     => array('label' => __('Registrar salidas de vehículos', 'aura-suite'), 'code' => 'exits_create'),
                    'aura_vehicles_km_update'        => array('label' => __('Actualizar kilometraje y bitácora', 'aura-suite'), 'code' => 'km_update'),
                    'aura_vehicles_exits_edit_own'   => array('label' => __('Editar propias salidas registradas', 'aura-suite'), 'code' => 'exits_edit_own'),
                    'aura_vehicles_exits_edit_all'   => array('label' => __('Editar todas las salidas', 'aura-suite'), 'code' => 'exits_edit_all'),
                    'aura_vehicles_edit'             => array('label' => __('Editar información de vehículos', 'aura-suite'), 'code' => 'edit'),
                    'aura_vehicles_audit'            => array('label' => __('Ver auditoría y trazabilidad', 'aura-suite'), 'code' => 'audit', 'star' => true),
                    'aura_vehicles_settings'         => array('label' => __('Configurar módulo de vehículos', 'aura-suite'), 'code' => 'settings', 'star' => true),
                    'aura_vehicles_exits_delete_own' => array('label' => __('Eliminar propias salidas', 'aura-suite'), 'code' => 'exits_delete_own'),
                    'aura_vehicles_exits_delete_all' => array('label' => __('Eliminar cualquier salida', 'aura-suite'), 'code' => 'exits_delete_all', 'star' => true),
                    'aura_vehicles_delete'           => array('label' => __('Eliminar vehículos', 'aura-suite'), 'code' => 'delete', 'star' => true),
                ),
            ),
            array(
                'group'        => 'operations',
                'module'       => 'library',
                'icon'         => '📚',
                'title'        => __('MÓDULO: BIBLIOTECA Y PRÉSTAMOS', 'aura-suite'),
                'capabilities' => array(
                    'aura_library_access'         => array('label' => __('Acceder al módulo de biblioteca', 'aura-suite'), 'code' => 'access'),
                    'aura_library_view_catalog'   => array('label' => __('Ver catálogo bibliográfico', 'aura-suite'), 'code' => 'view_catalog'),
                    'aura_library_view_loans_own' => array('label' => __('Ver mis préstamos propios', 'aura-suite'), 'code' => 'view_loans_own'),
                    'aura_library_view_loans_all' => array('label' => __('Ver todos los préstamos activos', 'aura-suite'), 'code' => 'view_loans_all'),
                    'aura_library_reports'        => array('label' => __('Ver reportes de biblioteca', 'aura-suite'), 'code' => 'reports'),
                    'aura_library_alerts'         => array('label' => __('Recibir alertas de préstamos vencidos', 'aura-suite'), 'code' => 'alerts'),
                    'aura_library_create'         => array('label' => __('Agregar libros al catálogo', 'aura-suite'), 'code' => 'create'),
                    'aura_library_loan_create'    => array('label' => __('Registrar préstamos de libros', 'aura-suite'), 'code' => 'loan_create'),
                    'aura_library_loan_return'    => array('label' => __('Registrar devolución de libros', 'aura-suite'), 'code' => 'loan_return'),
                    'aura_library_loan_extend'    => array('label' => __('Extender plazo de préstamo', 'aura-suite'), 'code' => 'loan_extend'),
                    'aura_library_loan_edit'      => array('label' => __('Editar datos de un préstamo', 'aura-suite'), 'code' => 'loan_edit', 'star' => true),
                    'aura_library_edit'           => array('label' => __('Editar información de libros', 'aura-suite'), 'code' => 'edit'),
                    'aura_library_audit'          => array('label' => __('Ver auditoría de biblioteca', 'aura-suite'), 'code' => 'audit', 'star' => true),
                    'aura_library_settings'       => array('label' => __('Configurar módulo de biblioteca', 'aura-suite'), 'code' => 'settings', 'star' => true),
                    'aura_library_loan_delete'    => array('label' => __('Cancelar / eliminar préstamos', 'aura-suite'), 'code' => 'loan_delete', 'star' => true),
                    'aura_library_delete'         => array('label' => __('Eliminar libros del catálogo', 'aura-suite'), 'code' => 'delete', 'star' => true),
                ),
            ),
            array(
                'group'        => 'operations',
                'module'       => 'electricity',
                'icon'         => '⚡',
                'title'        => __('MÓDULO: ELECTRICIDAD Y CONSUMOS', 'aura-suite'),
                'capabilities' => array(
                    'aura_electric_view_dashboard'    => array('label' => __('Ver dashboard de consumo', 'aura-suite'), 'code' => 'view_dashboard'),
                    'aura_electric_view_charts'       => array('label' => __('Ver gráficos de tendencias', 'aura-suite'), 'code' => 'view_charts'),
                    'aura_electric_alerts_receive'    => array('label' => __('Recibir alertas de consumo alto', 'aura-suite'), 'code' => 'alerts_receive'),
                    'aura_electric_export'            => array('label' => __('Exportar datos de consumo', 'aura-suite'), 'code' => 'export'),
                    'aura_electric_reading_create'    => array('label' => __('Registrar lecturas de contador', 'aura-suite'), 'code' => 'reading_create'),
                    'aura_electric_reading_edit_own'  => array('label' => __('Editar propias lecturas', 'aura-suite'), 'code' => 'reading_edit_own'),
                    'aura_electric_reading_edit_all'  => array('label' => __('Editar todas las lecturas', 'aura-suite'), 'code' => 'reading_edit_all'),
                    'aura_electric_thresholds_config' => array('label' => __('Configurar umbrales de alerta', 'aura-suite'), 'code' => 'thresholds_config', 'star' => true),
                    'aura_electric_reading_delete'    => array('label' => __('Eliminar lecturas registradas', 'aura-suite'), 'code' => 'reading_delete', 'star' => true),
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
            'treasury_assistant' => array(
                'name'          => __('Auxiliar Contable / Digitador', 'aura-suite'),
                'category'      => __('Finanzas & Captura', 'aura-suite'),
                'category_slug' => 'finance',
                'badge_class'   => 'aura-pill--info',
                'icon'          => 'dashicons-edit-page',
                'color'         => '#0284c7',
                'description'   => __('Ingreso de transacciones operativas sin acceso a saldos bancarios, totales de cuentas ni reportes de la organización.', 'aura-suite'),
                'highlights'    => array(
                    __('✅ Registrar transacciones e imputar conceptos', 'aura-suite'),
                    __('✅ Vincular usuarios y seleccionar o crear terceros', 'aura-suite'),
                    __('✅ Asignar etiquetas operativas a los gastos ingresados', 'aura-suite'),
                    __('✅ Ver y editar exclusivamente sus propios registros', 'aura-suite'),
                    __('🔒 Sin acceso a saldos bancarios, totales ni análisis globales', 'aura-suite'),
                    __('🔒 Sin permisos de aprobación ni conciliación', 'aura-suite'),
                ),
                'capabilities'  => array(
                    'aura_finance_create',
                    'aura_finance_edit_own',
                    'aura_finance_delete_own',
                    'aura_finance_view_own',
                    'aura_finance_link_user',
                    'aura_finance_tags',
                    'aura_third_parties_view',
                    'aura_third_parties_create',
                ),
            ),
            'treasurer' => array(
                'name'          => __('Tesorero / Administrador Financiero', 'aura-suite'),
                'category'      => __('Finanzas & Tesorería', 'aura-suite'),
                'category_slug' => 'finance',
                'badge_class'   => 'aura-pill--success',
                'icon'          => 'dashicons-vault',
                'color'         => '#059669',
                'description'   => __('Control total del flujo de caja, conciliaciones, cuentas bancarias, libro mayor, categorías, integraciones contables y aprobación.', 'aura-suite'),
                'highlights'    => array(
                    __('✅ Control total de transacciones, edición masiva y libro mayor', 'aura-suite'),
                    __('✅ Integraciones contables (QuickBooks, SAP, MX, Excel) y trazabilidad', 'aura-suite'),
                    __('✅ Cuentas bancarias, cajas chicas, saldos y presupuestos por área', 'aura-suite'),
                    __('✅ Importaciones masivas, categorías, terceros y auditoría contable', 'aura-suite'),
                ),
                'capabilities'  => array(
                    'aura_finance_create',
                    'aura_finance_edit_own',
                    'aura_finance_edit_all',
                    'aura_finance_bulk_edit',
                    'aura_finance_delete_own',
                    'aura_finance_delete_all',
                    'aura_finance_approve',
                    'aura_finance_view_own',
                    'aura_finance_view_all',
                    'aura_finance_charts',
                    'aura_finance_export',
                    'aura_finance_category_manage',
                    'aura_finance_integrations',
                    'aura_finance_audit',
                    'aura_finance_import',
                    'aura_finance_budgets',
                    'aura_finance_tags',
                    'aura_finance_accounts_manage',
                    'aura_finance_petty_cash_approve',
                    'aura_finance_exchange_view',
                    'aura_finance_exchange_create',
                    'aura_finance_exchange_edit',
                    'aura_finance_exchange_delete',
                    'aura_finance_link_user',
                    'aura_finance_user_ledger',
                    'aura_finance_view_user_summary',
                    'aura_finance_view_others_summary',
                    'aura_third_parties_view',
                    'aura_third_parties_create',
                    'aura_third_parties_edit',
                    'aura_third_parties_delete',
                    'aura_areas_view_all',
                    'aura_areas_budget_view',
                    'aura_areas_budget_manage',
                ),
            ),
            'director' => array(
                'name'          => __('Director General / Gerente', 'aura-suite'),
                'category'      => __('Dirección & Gobierno', 'aura-suite'),
                'category_slug' => 'direction',
                'badge_class'   => 'aura-pill--primary',
                'icon'          => 'dashicons-businessman',
                'color'         => '#4f46e5',
                'description'   => __('Visión ejecutiva 360°, aprobación de gastos de alto nivel, analíticas e informes consolidados de todos los módulos.', 'aura-suite'),
                'highlights'    => array(
                    __('✅ Aprobación ejecutiva de gastos y transacciones', 'aura-suite'),
                    __('✅ Tableros analíticos, presupuestos e informes consolidados', 'aura-suite'),
                    __('✅ Supervisión global de áreas, flota, inventario y cursos', 'aura-suite'),
                    __('✅ Consulta de libros mayores, trazabilidad y respuestas de encuestas', 'aura-suite'),
                ),
                'capabilities'  => array(
                    'aura_finance_approve',
                    'aura_finance_view_all',
                    'aura_finance_charts',
                    'aura_finance_export',
                    'aura_finance_user_ledger',
                    'aura_finance_view_others_summary',
                    'aura_finance_audit',
                    'aura_finance_budgets',
                    'aura_finance_petty_cash_approve',
                    'aura_finance_exchange_view',
                    'aura_admin_notifications_view',
                    'aura_third_parties_view',
                    'aura_areas_view_all',
                    'aura_areas_budget_view',
                    'aura_vehicles_view_all',
                    'aura_vehicles_reports',
                    'aura_electric_view_dashboard',
                    'aura_electric_view_charts',
                    'aura_forms_view_responses_all',
                    'aura_forms_analytics',
                    'aura_forms_reports',
                    'aura_inventory_view_all',
                    'aura_inventory_reports',
                    'aura_cert_view_all',
                    'aura_cert_reports',
                    'aura_students_view_all',
                    'aura_students_reports',
                    'aura_library_view_catalog',
                    'aura_library_reports',
                ),
            ),
            'auditor' => array(
                'name'          => __('Auditor / Revisor Fiscal', 'aura-suite'),
                'category'      => __('Auditoría & Control', 'aura-suite'),
                'category_slug' => 'audit',
                'badge_class'   => 'aura-pill--warning',
                'icon'          => 'dashicons-visibility',
                'color'         => '#d97706',
                'description'   => __('Acceso irrestricto de solo lectura a auditorías, libros mayores, integraciones contables, inventario y trazabilidad del sistema.', 'aura-suite'),
                'highlights'    => array(
                    __('✅ Lectura de todas las transacciones, cuentas y libros mayores', 'aura-suite'),
                    __('✅ Trazabilidad completa y auditoría de finanzas, vehículos y biblioteca', 'aura-suite'),
                    __('✅ Integraciones contables (QuickBooks, SAP, XML) y exportación fiscal', 'aura-suite'),
                    __('🔒 Estricto solo lectura: sin modificación ni borrado', 'aura-suite'),
                ),
                'capabilities'  => array(
                    'aura_finance_view_all',
                    'aura_finance_charts',
                    'aura_finance_export',
                    'aura_finance_user_ledger',
                    'aura_finance_view_others_summary',
                    'aura_finance_audit',
                    'aura_finance_integrations',
                    'aura_finance_budgets',
                    'aura_finance_tags',
                    'aura_finance_exchange_view',
                    'aura_third_parties_view',
                    'aura_areas_view_all',
                    'aura_areas_budget_view',
                    'aura_vehicles_view_all',
                    'aura_vehicles_reports',
                    'aura_vehicles_audit',
                    'aura_electric_view_dashboard',
                    'aura_electric_view_charts',
                    'aura_electric_export',
                    'aura_forms_view_responses_all',
                    'aura_forms_export',
                    'aura_forms_analytics',
                    'aura_forms_reports',
                    'aura_inventory_view_all',
                    'aura_inventory_reports',
                    'aura_inventory_cost_tracking',
                    'aura_inventory_lifecycle',
                    'aura_cert_view_all',
                    'aura_cert_reports',
                    'aura_students_view_all',
                    'aura_students_reports',
                    'aura_students_payments_view_all',
                    'aura_library_view_catalog',
                    'aura_library_reports',
                    'aura_library_audit',
                    'aura_admin_logs',
                ),
            ),
            'project_leader' => array(
                'name'          => __('Líder de Área / Coordinador', 'aura-suite'),
                'category'      => __('Proyectos & Áreas', 'aura-suite'),
                'category_slug' => 'projects',
                'badge_class'   => 'aura-pill--primary',
                'icon'          => 'dashicons-building',
                'color'         => '#7c3aed',
                'description'   => __('Gestión descentralizada del área asignada: ejecución de presupuesto, registro de gastos e inscripciones de su programa.', 'aura-suite'),
                'highlights'    => array(
                    __('✅ Consulta del presupuesto de su área asignada', 'aura-suite'),
                    __('✅ Registro de transacciones y etiquetas imputadas a su programa', 'aura-suite'),
                    __('✅ Formularios e inscripciones propios de su área', 'aura-suite'),
                    __('🔒 Acceso acotado a su área de responsabilidad', 'aura-suite'),
                ),
                'capabilities'  => array(
                    'aura_finance_create',
                    'aura_finance_edit_own',
                    'aura_finance_view_own',
                    'aura_finance_view_user_summary',
                    'aura_finance_tags',
                    'aura_finance_budgets',
                    'aura_third_parties_view',
                    'aura_third_parties_create',
                    'aura_areas_view_own',
                    'aura_areas_budget_view',
                    'aura_areas_forms_manage',
                    'aura_areas_enrollment_manage',
                    'aura_forms_submit',
                    'aura_forms_view_responses_all',
                ),
            ),
            'logistics_manager' => array(
                'name'          => __('Coordinador de Logística y Flota', 'aura-suite'),
                'category'      => __('Logística & Activos', 'aura-suite'),
                'category_slug' => 'logistics',
                'badge_class'   => 'aura-pill--warning',
                'icon'          => 'dashicons-car',
                'color'         => '#ea580c',
                'description'   => __('Administración completa de vehículos, salidas, kilometrajes, mantenimiento de equipos y consumo eléctrico.', 'aura-suite'),
                'highlights'    => array(
                    __('✅ Control total de vehículos, salidas y odómetros', 'aura-suite'),
                    __('✅ Préstamos, devoluciones y mantenimiento de herramientas', 'aura-suite'),
                    __('✅ Registro de lecturas eléctricas y alertas operativas', 'aura-suite'),
                    __('✅ Registro de compras y gastos menores de mantenimiento con etiquetas', 'aura-suite'),
                ),
                'capabilities'  => array(
                    'aura_vehicles_create',
                    'aura_vehicles_edit',
                    'aura_vehicles_exits_create',
                    'aura_vehicles_exits_edit_own',
                    'aura_vehicles_exits_edit_all',
                    'aura_vehicles_km_update',
                    'aura_vehicles_view_all',
                    'aura_vehicles_reports',
                    'aura_vehicles_alerts',
                    'aura_vehicles_audit',
                    'aura_inventory_create',
                    'aura_inventory_edit',
                    'aura_inventory_view_all',
                    'aura_inventory_checkout',
                    'aura_inventory_checkin',
                    'aura_inventory_loan_edit',
                    'aura_inventory_maintenance_create',
                    'aura_inventory_maintenance_edit',
                    'aura_inventory_maintenance_view',
                    'aura_inventory_maintenance_alerts',
                    'aura_inventory_maintenance_external',
                    'aura_inventory_reports',
                    'aura_electric_reading_create',
                    'aura_electric_reading_edit_own',
                    'aura_electric_view_dashboard',
                    'aura_third_parties_view',
                    'aura_third_parties_create',
                    'aura_finance_create',
                    'aura_finance_edit_own',
                    'aura_finance_view_own',
                    'aura_finance_tags',
                ),
            ),
            'academic_coordinator' => array(
                'name'          => __('Coordinador Académico & Estudiantes', 'aura-suite'),
                'category'      => __('Educación & Cursos', 'aura-suite'),
                'category_slug' => 'education',
                'badge_class'   => 'aura-pill--info',
                'icon'          => 'dashicons-welcome-learn-more',
                'color'         => '#0891b2',
                'description'   => __('Gestión de postulaciones estudiantiles, cursos, becas, cobro de cuotas educativas y emisión de diplomas certificados.', 'aura-suite'),
                'highlights'    => array(
                    __('✅ Registro, revisión y aprobación de estudiantes y cursos', 'aura-suite'),
                    __('✅ Asignación de becas y registro de pagos de cuotas', 'aura-suite'),
                    __('✅ Emisión, firma y descarga de certificados oficiales', 'aura-suite'),
                    __('✅ Asignación de encuestas educativas y analíticas', 'aura-suite'),
                ),
                'capabilities'  => array(
                    'aura_students_create',
                    'aura_students_edit',
                    'aura_students_view_all',
                    'aura_students_approve',
                    'aura_students_enrollments_manage',
                    'aura_students_scholarships_view',
                    'aura_students_scholarships_assign',
                    'aura_students_payments_register',
                    'aura_students_payments_view_all',
                    'aura_students_quotas_config',
                    'aura_students_status_view',
                    'aura_students_courses_manage',
                    'aura_students_reports',
                    'aura_cert_template_view',
                    'aura_cert_template_create',
                    'aura_cert_template_edit',
                    'aura_cert_issue',
                    'aura_cert_view_all',
                    'aura_cert_download_any',
                    'aura_cert_signatures_manage',
                    'aura_cert_reports',
                    'aura_forms_assign',
                    'aura_forms_enrollment_review',
                    'aura_forms_view_responses_all',
                    'aura_forms_analytics',
                ),
            ),
            'librarian' => array(
                'name'          => __('Bibliotecario / Gestor Documental', 'aura-suite'),
                'category'      => __('Biblioteca & Acervo', 'aura-suite'),
                'category_slug' => 'library',
                'badge_class'   => 'aura-pill--muted',
                'icon'          => 'dashicons-book-alt',
                'color'         => '#16a34a',
                'description'   => __('Gestión integral del catálogo bibliográfico, control de préstamos a estudiantes/docentes, devoluciones y alertas de mora.', 'aura-suite'),
                'highlights'    => array(
                    __('✅ Catálogo completo de libros, fichas y categorías', 'aura-suite'),
                    __('✅ Registro de préstamos, renovaciones y devoluciones', 'aura-suite'),
                    __('✅ Alertas automáticas de préstamos vencidos', 'aura-suite'),
                    __('✅ Reportes estadísticos de lectura y circulación', 'aura-suite'),
                ),
                'capabilities'  => array(
                    'aura_library_access',
                    'aura_library_create',
                    'aura_library_edit',
                    'aura_library_delete',
                    'aura_library_view_catalog',
                    'aura_library_loan_create',
                    'aura_library_loan_return',
                    'aura_library_loan_extend',
                    'aura_library_loan_edit',
                    'aura_library_loan_delete',
                    'aura_library_view_loans_all',
                    'aura_library_reports',
                    'aura_library_alerts',
                    'aura_library_settings',
                    'aura_library_audit',
                ),
            ),
            'field_operator' => array(
                'name'          => __('Operador de Campo / Técnico', 'aura-suite'),
                'category'      => __('Operación de Campo', 'aura-suite'),
                'category_slug' => 'field',
                'badge_class'   => 'aura-pill--muted',
                'icon'          => 'dashicons-location-alt',
                'color'         => '#64748b',
                'description'   => __('Operación básica para conductores y técnicos: registro de salidas vehiculares, odómetros, lecturas eléctricas y encuestas.', 'aura-suite'),
                'highlights'    => array(
                    __('✅ Registrar salidas vehiculares y actualizar odómetros', 'aura-suite'),
                    __('✅ Toma de lecturas de contadores eléctricos', 'aura-suite'),
                    __('✅ Diligenciar formularios y solicitudes en campo', 'aura-suite'),
                    __('🔒 Sin acceso a información contable ni datos sensibles', 'aura-suite'),
                ),
                'capabilities'  => array(
                    'aura_vehicles_exits_create',
                    'aura_vehicles_km_update',
                    'aura_vehicles_view_all',
                    'aura_electric_reading_create',
                    'aura_electric_view_dashboard',
                    'aura_forms_submit',
                    'aura_inventory_view_all',
                    'aura_inventory_checkout',
                    'aura_inventory_checkin',
                ),
            ),
            'system_admin' => array(
                'name'          => __('Administrador de Sistemas & TI', 'aura-suite'),
                'category'      => __('Tecnología & Seguridad', 'aura-suite'),
                'category_slug' => 'admin',
                'badge_class'   => 'aura-pill--danger',
                'icon'          => 'dashicons-shield-alt',
                'color'         => '#dc2626',
                'description'   => __('Gestión técnica de usuarios, asignación de permisos CBAC, copias de seguridad, logs de auditoría, integraciones y activación de módulos.', 'aura-suite'),
                'highlights'    => array(
                    __('✅ Creación de usuarios y asignación de permisos CBAC granulares', 'aura-suite'),
                    __('✅ Gobierno de integraciones contables, Google Drive y módulos', 'aura-suite'),
                    __('✅ Copias de seguridad y logs de auditoría técnica y de seguridad', 'aura-suite'),
                    __('✅ Configuración maestra y administración global de la plataforma', 'aura-suite'),
                ),
                'capabilities'  => array(
                    'aura_admin_users_create',
                    'aura_admin_users_manage',
                    'aura_admin_permissions_assign',
                    'aura_admin_settings',
                    'aura_admin_gdrive_config',
                    'aura_admin_notifications_view',
                    'aura_admin_modules_enable',
                    'aura_admin_backup',
                    'aura_admin_logs',
                    'aura_finance_integrations',
                    'aura_finance_audit',
                    'aura_areas_manage',
                ),
            ),
            'area_budget_operator' => array(
                'name'          => __('Gestor de Presupuesto de Área / Proyecto', 'aura-suite'),
                'category'      => __('Presupuestos & Áreas', 'aura-suite'),
                'category_slug' => 'projects',
                'badge_class'   => 'aura-pill--primary',
                'icon'          => 'dashicons-chart-pie',
                'color'         => '#0284c7',
                'description'   => __('Permite registrar y justificar transacciones imputadas única y exclusivamente al presupuesto del área o proyecto asignado al usuario, con consulta en tiempo real del saldo disponible sin acceso a otras áreas ni a cuentas globales.', 'aura-suite'),
                'highlights'    => array(
                    __('✅ Registro de transacciones acotado al presupuesto de su área asignada', 'aura-suite'),
                    __('✅ Consulta del techo presupuestal y saldo restante de su proyecto', 'aura-suite'),
                    __('✅ Gestión y edición exclusiva de sus propios comprobantes y gastos', 'aura-suite'),
                    __('🔒 Restricción jerárquica: no puede ver ni cargar gastos a otras áreas', 'aura-suite'),
                    __('🔒 Sin acceso a cuentas bancarias de la empresa, libro mayor ni auditoría', 'aura-suite'),
                ),
                'capabilities'  => array(
                    'aura_finance_create',
                    'aura_finance_edit_own',
                    'aura_finance_view_own',
                    'aura_finance_view_user_summary',
                    'aura_finance_tags',
                    'aura_areas_view_own',
                    'aura_areas_budget_view',
                    'aura_third_parties_view',
                    'aura_third_parties_create',
                ),
            ),
        );
    }
    
    /**
     * Asignar plantilla de perfil a usuario
     * 
     * @param int    $user_id     ID del usuario
     * @param string $template_id ID de la plantilla
     * @param bool   $replace     Si se deben remover primero las capabilities previas de Aura
     * @return bool
     */
    public static function assign_template_to_user($user_id, $template_id, $replace = true) {
        $templates = self::get_profile_templates();
        
        if (!isset($templates[$template_id])) {
            return false;
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $capabilities = $templates[$template_id]['capabilities'];
        
        // Si se solicita reemplazo, remover primero todas las capabilities de Aura del usuario
        if ($replace) {
            $all_caps = self::get_all_capabilities();
            foreach ($all_caps as $module => $caps) {
                foreach ($caps as $cap => $desc) {
                    $user->remove_cap($cap);
                }
            }
        }
        
        foreach ($capabilities as $cap) {
            $user->add_cap($cap);
        }
        
        return true;
    }

    /**
     * Endpoint AJAX para aplicar una plantilla de perfil predefinido a un usuario
     */
    public static function ajax_apply_profile_template() {
        check_ajax_referer('aura_permissions_nonce', 'nonce');

        if (!current_user_can('aura_admin_permissions_assign') && !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos suficientes para asignar capabilities a usuarios.', 'aura-suite')
            ));
        }

        $user_id     = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $template_id = isset($_POST['template_id']) ? sanitize_key($_POST['template_id']) : '';

        if (!$user_id || empty($template_id)) {
            wp_send_json_error(array(
                'message' => __('Identificador de usuario o plantilla no proporcionado.', 'aura-suite')
            ));
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_send_json_error(array(
                'message' => __('El usuario especificado no existe en el sistema.', 'aura-suite')
            ));
        }

        $templates = self::get_profile_templates();
        if (!isset($templates[$template_id])) {
            wp_send_json_error(array(
                'message' => __('La plantilla de perfil seleccionada no existe.', 'aura-suite')
            ));
        }

        $success = self::assign_template_to_user($user_id, $template_id, true);

        if ($success) {
            $tpl = $templates[$template_id];
            $caps_count = count($tpl['capabilities']);
            wp_send_json_success(array(
                'message'       => sprintf(
                    __('¡Plantilla "%s" (%d permisos) aplicada y guardada exitosamente para %s!', 'aura-suite'),
                    $tpl['name'],
                    $caps_count,
                    $user->display_name
                ),
                'user_id'       => $user_id,
                'user_name'     => $user->display_name,
                'template_id'   => $template_id,
                'template_name' => $tpl['name'],
                'capabilities'  => $tpl['capabilities'],
                'redirect_url'  => add_query_arg(array(
                    'page'    => 'aura-permissions',
                    'user_id' => $user_id,
                    'updated' => 'true',
                    'tab'     => 'user-permissions'
                ), admin_url('admin.php'))
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Ocurrió un error al guardar la plantilla para este usuario.', 'aura-suite')
            ));
        }
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
     * Conceder dinámicamente `manage_dashboard` y `read` a cualquier usuario
     * que tenga al menos una capability de Aura Suite.
     *
     * WordPress exige `manage_dashboard` para acceder a wp-admin/index.php y
     * muestra "No tienes permisos" si esa cap falta, incluso si WooCommerce
     * ya permite el acceso al admin. Esta función resuelve ese bloqueo sin
     * escribir nada en la base de datos.
     *
     * @param bool[]   $allcaps Capabilities actuales del usuario.
     * @param string[] $caps    Capabilities requeridas en la comprobación.
     * @param array    $args    Argumentos adicionales.
     * @param WP_User  $user    Objeto del usuario.
     * @return bool[] Capabilities modificadas.
     */
    public static function grant_admin_dashboard_cap( $allcaps, $caps, $args, $user ) {
        // Si ya tiene manage_dashboard no hay nada que hacer.
        if ( ! empty( $allcaps['manage_dashboard'] ) ) {
            return $allcaps;
        }

        // Solo actuar cuando WordPress comprueba manage_dashboard o read.
        if ( ! in_array( 'manage_dashboard', (array) $caps, true )
             && ! in_array( 'read', (array) $caps, true ) ) {
            return $allcaps;
        }

        // Verificar si el usuario tiene al menos una capability de Aura.
        $aura_caps = self::get_all_capabilities();
        foreach ( $aura_caps as $module => $module_caps ) {
            foreach ( $module_caps as $cap => $description ) {
                if ( ! empty( $allcaps[ $cap ] ) ) {
                    // Usuario Aura → conceder acceso al admin dashboard.
                    $allcaps['manage_dashboard'] = true;
                    $allcaps['read']             = true;
                    return $allcaps;
                }
            }
        }

        return $allcaps;
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
            'aura_third_parties_create',
            'aura_third_parties_edit',
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
