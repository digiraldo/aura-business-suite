<?php
/**
 * Plugin Name: Aura Business Suite
 * Plugin URI: https://profiles.wordpress.org/digiraldo/
 * Description: Suite modular de gestion empresarial con permisos granulares (CBAC) - Modulos: Finanzas, Vehiculos, Formularios, Electricidad, Areas/Programas Multi-Usuario
 * Version: 1.7.9
 * Author: DiGiraldo
 * Author URI: https://github.com/digiraldo/aura-business-suite
 * Text Domain: aura-suite
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package AuraBusinessSuite
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('AURA_VERSION', '1.7.9');
define('AURA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AURA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AURA_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Clase principal del plugin Aura Business Suite
 */
class Aura_Business_Suite {
    
    /**
     * Instancia única del plugin (Singleton)
     * 
     * @var Aura_Business_Suite
     */
    private static $instance = null;
    
    /**
     * Obtener instancia única del plugin
     * 
     * @return Aura_Business_Suite
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor privado (Singleton)
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Cargar dependencias del plugin
     */
    private function load_dependencies() {
        // Autoloader de Composer (PhpSpreadsheet, Google Client, etc.)
        $autoload = AURA_PLUGIN_DIR . 'vendor/autoload.php';
        if ( file_exists( $autoload ) ) {
            try {
                require_once $autoload;
            } catch ( \Throwable $e ) {
                error_log( 'Aura Business Suite - Error cargando vendor/autoload.php: ' . $e->getMessage() );
            }
        }

        // Módulos comunes
        require_once AURA_PLUGIN_DIR . 'modules/common/class-aura-ui.php';
        require_once AURA_PLUGIN_DIR . 'modules/common/class-aura-list-table.php';
        require_once AURA_PLUGIN_DIR . 'modules/common/class-roles-manager.php';
        require_once AURA_PLUGIN_DIR . 'modules/common/class-notifications.php';
        require_once AURA_PLUGIN_DIR . 'modules/common/class-google-calendar.php';
        require_once AURA_PLUGIN_DIR . 'modules/common/class-third-parties.php';
        
        // Módulo Financiero
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-cpt.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-categories-cpt.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-categories.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-categories-api.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-dashboard.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-reports.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-charts.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-analytics.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-export.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-import.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-budgets.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-tags.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-search.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-audit.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-notifications.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-integrations.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-setup.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-transactions.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-transactions-ajax.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-transactions-update.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-transactions-delete.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-approval.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-settings.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-google-drive-manager.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-capital.php';   // 🏗️ CapEx
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-user-dashboard.php'; // Fase 6, Item 6.2
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-user-ledger.php';     // Fase 6, Item 6.3
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-accounts.php';        // Fase 0/1 Bancos y cuentas
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-usd-ledger.php';         // Caja Chica USD
        
        // Cargar WP_List_Table si estamos en admin
        if (is_admin()) {
            require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-transactions-list.php';
            require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-trash-list.php';
            require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-pending-list.php';
        }
        
        // Módulo de Áreas y Programas (Fase 7)
        require_once AURA_PLUGIN_DIR . 'modules/areas/class-areas-setup.php';
        require_once AURA_PLUGIN_DIR . 'modules/areas/class-areas-admin.php';  // Ítem 7.2 — Admin UI
        require_once AURA_PLUGIN_DIR . 'modules/areas/class-areas-types.php';  // Ítem 7.3 — Tipos de Área

        // Módulo de Vehículos — Fase 1
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-cpt.php';        // conservado como referencia, no se llama init()
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-setup.php';
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-module.php';
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/admin/class-vehicle-admin.php';
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-alerts.php';
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-reports.php';
        // Módulo de Vehículos — Fase 2
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-audit-manager.php';
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-manager.php';
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/api/class-vehicle-rest-vehicles.php';
        // Módulo de Vehículos — Fase 3
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-trip-manager.php';
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/api/class-vehicle-rest-trips.php';
        // Módulo de Vehículos — Fase 4
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-catalog-manager.php';
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/api/class-vehicle-rest-catalogs.php';
        // Módulo de Vehículos — Fase 5
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/api/class-vehicle-rest-stats.php';
        // Módulo de Vehículos — Fase 6
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/api/class-vehicle-rest-reports.php';
        // Módulo de Vehículos — Fase 7
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/api/class-vehicle-rest-audit.php';
        // Módulo de Vehículos — Fase 9
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/api/class-vehicle-rest-settings.php';
        // Módulo de Vehículos — Fase QR
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/api/class-vehicle-rest-qr.php';
        // Módulo de Vehículos — Fase 10
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-financial-bridge.php';
        
        // Módulo de Electricidad
        require_once AURA_PLUGIN_DIR . 'modules/electricity/class-electricity-cpt.php';
        require_once AURA_PLUGIN_DIR . 'modules/electricity/class-electricity-api.php';
        require_once AURA_PLUGIN_DIR . 'modules/electricity/class-electricity-dashboard.php';

        // Módulo de Biblioteca — Fase 1
        require_once AURA_PLUGIN_DIR . 'modules/library/class-library-setup.php';
        require_once AURA_PLUGIN_DIR . 'modules/library/class-library-module.php';
        require_once AURA_PLUGIN_DIR . 'modules/library/admin/class-library-admin.php';
        // Módulo de Biblioteca — Fase 2: Catálogo
        require_once AURA_PLUGIN_DIR . 'modules/library/admin/class-library-books.php';
        // Módulo de Biblioteca — Fase 3: Préstamos y Devoluciones
        require_once AURA_PLUGIN_DIR . 'modules/library/admin/class-library-fines.php';
        require_once AURA_PLUGIN_DIR . 'modules/library/admin/class-library-loans.php';
        // Módulo de Biblioteca — Fase 4: Reservas
        require_once AURA_PLUGIN_DIR . 'modules/library/admin/class-library-reservations.php';
        // Módulo de Biblioteca — Fase 5: Notificaciones y Cron
        require_once AURA_PLUGIN_DIR . 'modules/library/admin/class-library-notifications.php';
        require_once AURA_PLUGIN_DIR . 'modules/library/admin/class-library-cron.php';
        // Módulo de Biblioteca — Fase 6: Dashboard y Reportes
        require_once AURA_PLUGIN_DIR . 'modules/library/admin/class-library-reports.php';
        // Módulo de Biblioteca — Fase 8: Auditoría + Configuración + REST API
        require_once AURA_PLUGIN_DIR . 'modules/library/admin/class-library-audit.php';
        require_once AURA_PLUGIN_DIR . 'modules/library/admin/class-library-settings.php';
        require_once AURA_PLUGIN_DIR . 'modules/library/class-library-api.php';

        // Módulo de Inventario y Mantenimientos (FASE 1+)
        require_once AURA_PLUGIN_DIR . 'modules/inventory/class-inventory-setup.php';
        // Módulo de Inventario — FASE 2: Dashboard + Equipos
        require_once AURA_PLUGIN_DIR . 'modules/inventory/class-inventory-dashboard.php';
        require_once AURA_PLUGIN_DIR . 'modules/inventory/class-inventory-equipment.php';
        // Módulo de Inventario — FASE 3: Mantenimientos
        require_once AURA_PLUGIN_DIR . 'modules/inventory/class-inventory-maintenance.php';
        // Módulo de Inventario — FASE 5: Préstamos (Checkout/Checkin)
        require_once AURA_PLUGIN_DIR . 'modules/inventory/class-inventory-loans.php';
        // Módulo de Inventario — FASE 6: Alertas y Notificaciones
        require_once AURA_PLUGIN_DIR . 'modules/inventory/class-inventory-notifications.php';
        // Módulo de Inventario — FASE 6+: Integración Google Calendar
        require_once AURA_PLUGIN_DIR . 'modules/inventory/class-inventory-google-calendar.php';
        // Módulo de Inventario — FASE 7: Reportes
        require_once AURA_PLUGIN_DIR . 'modules/inventory/class-inventory-reports.php';
        // Módulo de Inventario — Configuración y Categorías
        require_once AURA_PLUGIN_DIR . 'modules/inventory/class-inventory-categories.php';

        // ── Módulo de Estudiantes e Inscripciones ─────────────────
        require_once AURA_PLUGIN_DIR . 'modules/students/class-students-setup.php';
        require_once AURA_PLUGIN_DIR . 'modules/students/class-students-admin.php';
        require_once AURA_PLUGIN_DIR . 'modules/students/class-students-dashboard.php';
        require_once AURA_PLUGIN_DIR . 'modules/students/class-students-courses.php';
        require_once AURA_PLUGIN_DIR . 'modules/students/class-students-crud.php';
        require_once AURA_PLUGIN_DIR . 'modules/students/class-students-enrollments.php';
        require_once AURA_PLUGIN_DIR . 'modules/students/class-students-payments.php';
        require_once AURA_PLUGIN_DIR . 'modules/students/class-students-scholarships.php';
        require_once AURA_PLUGIN_DIR . 'modules/students/class-students-frontend.php';
        require_once AURA_PLUGIN_DIR . 'modules/students/class-students-notifications.php';
        require_once AURA_PLUGIN_DIR . 'modules/students/class-students-reports.php';
        require_once AURA_PLUGIN_DIR . 'modules/students/class-students-settings.php';

        // ── Módulo de Certificados y Diplomas ──────────────────────
        require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-settings.php';
        require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-setup.php';
        require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-admin.php';
        require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-templates.php';
        require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-signers.php';
        require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-folio.php';
        require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-issuer.php';
        require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-verify.php';
        require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-frontend.php';
        require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-notifications.php';
        require_once AURA_PLUGIN_DIR . 'modules/certificates/class-certificates-reports.php';

        // ── Módulo de Formularios y Encuestas ──────────────────────
        require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-setup.php';
        require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-admin.php';
        require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-builder.php';
        require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-submissions.php';
        require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-assignments.php';
        require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-enrollment.php';
        require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-analytics.php';
        require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-export.php';
        require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-frontend.php';
        require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-notifications.php';
        require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-reports.php';
        require_once AURA_PLUGIN_DIR . 'modules/forms/class-forms-settings.php';
    }
    
    /**
     * Inicializar hooks de WordPress
     */
    private function init_hooks() {
        // Activación y desactivación del plugin
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Hook de inicialización
        add_action('init', array($this, 'init'));
        
        // Cargar traducciones
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Encolar scripts y estilos en admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Encolar scripts y estilos en frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Logo de la organización en login (configurable desde Ajustes)
        add_action('login_enqueue_scripts', array($this, 'org_login_logo'));
        
        // Menú de administración y estilo del icono
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_head', array($this, 'output_admin_menu_icon_styles'));
        
        // Hooks AJAX para reinstalación de categorías
        add_action('wp_ajax_aura_reinstall_categories', array($this, 'ajax_reinstall_categories'));
        
        // Hook AJAX para guardar excepciones de categorías
        add_action('wp_ajax_aura_save_category_exceptions', array($this, 'ajax_save_category_exceptions'));

        // Hook AJAX para refresh de stats del dashboard principal
        add_action('wp_ajax_aura_dashboard_refresh_stats', array($this, 'ajax_dashboard_refresh_stats'));

        // Hook AJAX para crear un nuevo usuario (por usuarios con aura_admin_users_create)
        add_action('wp_ajax_aura_create_user', array($this, 'ajax_create_user'));
    }
    
    /**
     * Inicializar módulos del plugin
     */
    public function init() {
        // Inicializar sistema de roles y capabilities
        Aura_Roles_Manager::init();
        
        // Inicializar directorio global de terceros
        Aura_Third_Parties::init();
        
        Aura_Financial_Categories_CPT::init();
        Aura_Financial_Categories::get_instance();
        Aura_Electricity_CPT::init();
        
        // Inicializar REST API
        Aura_Financial_Categories_API::init();
        Aura_Electricity_API::init();
        
        // Inicializar sistema de notificaciones
        Aura_Notifications::init();
        Aura_Google_Calendar::init();
        
        // Módulo de Vehículos — Fase 1 + 2 + 3
        Aura_Vehicle_Module::get_instance();
        Aura_Vehicle_Rest_Vehicles::init();
        Aura_Vehicle_Rest_Trips::init();
        
        // Inicializar sistema de aprobación de transacciones
        Aura_Financial_Approval::init();
        
        // Inicializar sistema de configuraciones del módulo financiero
        Aura_Financial_Settings::init();
        
        // Inicializar sistema de eliminación/papelera de transacciones
        Aura_Financial_Transactions_Delete::init();

        // Inicializar dashboard financiero (registra AJAX)
        Aura_Financial_Dashboard::init();

        // Inicializar reportes financieros (registra AJAX + cron)
        Aura_Financial_Reports::init();

        // Inicializar análisis visual (Fase 3, Item 3.3)
        Aura_Financial_Analytics::init();

        // Inicializar exportación multi-formato (Fase 4, Item 4.1)
        Aura_Financial_Export::init();

        // Inicializar importación CSV/Excel (Fase 4, Item 4.2)
        Aura_Financial_Import::init();

        // Inicializar presupuestos por categoría (Fase 5, Item 5.1)
        Aura_Financial_Budgets::init();

        // Inicializar etiquetas y búsqueda avanzada (Fase 5, Item 5.2)
        Aura_Financial_Tags::init();
        Aura_Financial_Search::init();

        // Inicializar auditoría y trazabilidad (Fase 5, Item 5.3)
        Aura_Financial_Audit::init();

        // Inicializar Áreas y Programas — migración BD (Fase 7, Ítem 7.1)
        Aura_Areas_Setup::init();

        // Inicializar Áreas y Programas — Admin UI (Fase 7, Ítem 7.2)
        Aura_Areas_Admin::init();

        // Inicializar Áreas y Programas — Tipos de Área CRUD (Fase 7, Ítem 7.3)
        Aura_Areas_Types::init();

        // Inicializar notificaciones y recordatorios (Fase 5, Item 5.4)
        Aura_Financial_Notifications::init();

        // Inicializar integraciones contables (Fase 5, Item 5.5)
        Aura_Financial_Integrations::init();

        // Inicializar Dashboard Financiero Personal del Usuario (Fase 6, Item 6.2)
        Aura_Financial_User_Dashboard::init();

        // Inicializar Libro Mayor por Usuario (Fase 6, Item 6.3)
        Aura_Financial_User_Ledger::init();

        // Inicializar Bancos y Cuentas (Fase 0 + Fase 1)
        Aura_Financial_Accounts::init();

        // Inicializar Caja Chica USD → MXN
        Aura_Financial_USD_Ledger::init();

        // Registrar tamaños de imagen personalizados para equipos
        add_image_size( 'aura-equipment-full',  800, 600, true );  // vista en modal/formulario
        add_image_size( 'aura-equipment-thumb', 220, 165, true );  // miniatura en tablas

        // Inicializar Módulo de Inventario y Mantenimientos
        Aura_Inventory_Setup::init();
        Aura_Inventory_Dashboard::init();
        Aura_Inventory_Equipment::init();
        // Inicializar gestión de Mantenimientos (FASE 3)
        Aura_Inventory_Maintenance::init();
        // Inicializar gestión de Préstamos (FASE 5)
        Aura_Inventory_Loans::init();
        // Inicializar Alertas y Notificaciones (FASE 6)
        Aura_Inventory_Notifications::init();
        // Inicializar Integración Google Calendar (FASE 6+)
        Aura_Inventory_Google_Calendar::init();
        // Inicializar Reportes de Inventario (FASE 7)
        Aura_Inventory_Reports::init();
        // Inicializar Configuración y Categorías del Inventario
        Aura_Inventory_Categories::init();

        // ── Módulo de Estudiantes e Inscripciones ─────────────────
        Aura_Students_Setup::init();
        Aura_Students_Admin::init();
        Aura_Students_Dashboard::init();
        Aura_Students_Courses::init();
        Aura_Students_CRUD::init();
        Aura_Students_Enrollments::init();
        Aura_Students_Payments::init();
        Aura_Students_Scholarships::init();
        Aura_Students_Frontend::init();
        Aura_Students_Notifications::init();
        Aura_Students_Reports::init();
        Aura_Students_Settings::init();

        // ── Módulo de Certificados y Diplomas ──────────────────────
        Aura_Certificates_Setup::init();
        Aura_Certificates_Admin::init();
        Aura_Certificates_Templates::init();
        Aura_Certificates_Signers::init();
        Aura_Certificates_Issuer::init();
        Aura_Certificates_Verify::init();
        Aura_Certificates_Frontend::init();
        Aura_Certificates_Notifications::init();
        Aura_Certificates_Reports::init();
        Aura_Certificates_Settings::init();

        // ── Módulo de Formularios y Encuestas ──────────────────────
        Aura_Forms_Setup::init();
        Aura_Forms_Admin::init();
        Aura_Forms_Builder::init();
        Aura_Forms_Submissions::init();
        Aura_Forms_Assignments::init();
        Aura_Forms_Enrollment::init();
        Aura_Forms_Analytics::init();
        Aura_Forms_Export::init();
        Aura_Forms_Frontend::init();
        Aura_Forms_Notifications::init();
        Aura_Forms_Reports::init();
        Aura_Forms_Settings::init();

        // Módulo de Biblioteca — Fase 1
        Aura_Library_Module::get_instance();
    }

    /**
     * Activación del plugin
     */
    public function activate() {
        // Registrar capabilities en la base de datos
        Aura_Roles_Manager::register_all_capabilities();
        
        // Crear tablas de base de datos
        Aura_Financial_Categories_CPT::create_categories_table();
        Aura_Financial_Transactions::create_transactions_table();
        Aura_Financial_USD_Ledger::create_table();
        Aura_Inventory_Setup::create_tables();
        Aura_Students_Setup::create_tables();
        Aura_Certificates_Setup::create_tables();
        Aura_Forms_Setup::create_tables();
        Aura_Vehicle_Setup::create_tables();
        Aura_Library_Setup::create_tables();
        
        // Instalar categorías financieras predeterminadas
        $this->install_default_categories();
        
        // Crear páginas necesarias si no existen
        $this->create_required_pages();
        
        // Flush rewrite rules para que funcionen los permalinks de CPTs
        flush_rewrite_rules();
        
        // Agregar opción de versión
        add_option('aura_version', AURA_VERSION);

        // Programar cron diario de expiración de reservas de Biblioteca (Fase 4)
        if ( ! wp_next_scheduled( 'aura_library_expire_reservations' ) ) {
            wp_schedule_event( time(), 'daily', 'aura_library_expire_reservations' );
        }

        // Programar cron diario de alertas de préstamos de Biblioteca (Fase 5)
        // La hora exacta la determina schedule_cron_jobs() en el hook 'wp'.
        // Forzamos la primera programación en activación.
        if ( class_exists( 'Aura_Library_Cron' ) && ! wp_next_scheduled( 'aura_library_daily_cron' ) ) {
            Aura_Library_Cron::schedule_cron_jobs();
        }
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Eliminar eventos cron programados
        wp_clear_scheduled_hook('aura_daily_vehicle_alerts');
        wp_clear_scheduled_hook('aura_daily_electricity_alerts');

        // Eliminar crons del módulo de inventario (FASE 6)
        Aura_Inventory_Notifications::clear_cron_jobs();

        // Eliminar cron de reservas de Biblioteca (Fase 4)
        wp_clear_scheduled_hook( 'aura_library_expire_reservations' );

        // Eliminar cron de alertas de préstamos de Biblioteca (Fase 5)
        wp_clear_scheduled_hook( 'aura_library_daily_cron' );
    }
    
    /**
     * Instalar categorías financieras predeterminadas
     * 
     * @param bool $force_reinstall Forzar reinstalación de categorías
     * @return array Resultado de la instalación con éxito y mensaje
     */
    private function install_default_categories($force_reinstall = false) {
        // Verificar si ya se instalaron previamente
        $installed_version = get_option('aura_finance_categories_installed', false);
        
        // Si ya están instaladas y no se fuerza reinstalación, salir
        if ($installed_version && !$force_reinstall) {
            return array(
                'success' => true,
                'message' => __('Las categorías ya están instaladas', 'aura-suite'),
                'version' => $installed_version
            );
        }
        
        // Instanciar clase de configuración
        $setup = new Aura_Financial_Setup();
        
        // Instalar categorías
        $result = $setup->install_default_categories($force_reinstall);
        
        // Si la instalación fue exitosa, guardar versión
        if ($result['success']) {
            update_option('aura_finance_categories_installed', AURA_VERSION);
            
            // Log de instalación
            error_log('AURA: Categorías financieras instaladas correctamente - ' . 
                     $result['stats']['total'] . ' categorías creadas');
        }
        
        return $result;
    }
    
    /**
     * Handler AJAX para reinstalar categorías financieras
     * 
     * Usado desde la página de configuración para permitir reinstalar
     * las categorías predeterminadas sin desactivar/activar el plugin
     */
    public function ajax_reinstall_categories() {
        // Verificar nonce para seguridad
        check_ajax_referer('aura_reinstall_categories_nonce', 'nonce');
        
        // Verificar permisos
        if (!current_user_can('aura_admin_settings')) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para realizar esta acción', 'aura-suite')
            ));
            return;
        }
        
        // Reinstalar categorías (forzar)
        $result = $this->install_default_categories(true);
        
        // Enviar respuesta
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'stats' => $result['stats']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
                'errors' => isset($result['errors']) ? $result['errors'] : array()
            ));
        }
    }
    
    /**
     * Handler AJAX para guardar excepciones de categorías
     * 
     * Actualiza el campo 'always_require_approval' en las categorías seleccionadas
     * para que siempre requieran aprobación manual independientemente del umbral
     */
    public function ajax_save_category_exceptions() {
        // Verificar nonce para seguridad
        check_ajax_referer('aura_category_exceptions', 'nonce');
        
        // Verificar permisos
        if (!current_user_can('aura_admin_settings')) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para realizar esta acción', 'aura-suite')
            ));
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';
        
        // Obtener IDs de categorías marcadas
        $category_ids = isset($_POST['category_ids']) && is_array($_POST['category_ids']) 
                        ? array_map('intval', $_POST['category_ids']) 
                        : array();
        
        // Primero, quitar el flag de todas las categorías
        $wpdb->query(
            "UPDATE $table SET always_require_approval = 0 WHERE is_active = 1"
        );
        
        // Luego, activar el flag solo en las categorías seleccionadas
        if (!empty($category_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($category_ids), '%d'));
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $table SET always_require_approval = 1 WHERE id IN ($ids_placeholder)",
                    ...$category_ids
                )
            );
        }
        
        // Contar categorías actualizadas
        $count = count($category_ids);
        
        wp_send_json_success(array(
            'message' => sprintf(
                _n(
                    'Se configuró %d categoría para requerir aprobación manual.',
                    'Se configuraron %d categorías para requerir aprobación manual.',
                    $count,
                    'aura-suite'
                ),
                $count
            ),
            'count' => $count
        ));
    }
    
    /**
     * Crear páginas necesarias del plugin
     */
    private function create_required_pages() {
        // Dashboard principal
        $dashboard_page = array(
            'post_title'   => __('Dashboard Aura', 'aura-suite'),
            'post_content' => '[aura_main_dashboard]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => 1,
        );
        
        // Verificar si ya existe
        $existing_page = get_page_by_title('Dashboard Aura');
        if (!$existing_page) {
            wp_insert_post($dashboard_page);
        }
    }
    
    /**
     * Cargar traducciones
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'aura-suite',
            false,
            dirname(AURA_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Encolar assets del admin
     * 
     * @param string $hook Página actual del admin
     */
    /**
     * AJAX: Retorna stats actualizadas para el dashboard principal.
     */
    public function ajax_dashboard_refresh_stats() {
        check_ajax_referer( 'aura_dashboard_nonce', 'nonce' );

        global $wpdb;

        $data = [];

        // Notificaciones no leídas
        $notif_table = $wpdb->prefix . 'aura_notifications';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$notif_table}'" ) === $notif_table ) {
            $data['notifications'] = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$notif_table} WHERE user_id = %d AND is_read = 0",
                get_current_user_id()
            ) );
        } else {
            $data['notifications'] = 0;
        }

        // Finanzas
        if ( Aura_Roles_Manager::user_can_view_module( 'finance' ) ) {
            $month_start = date( 'Y-m-01 00:00:00' );
            $month_end   = date( 'Y-m-t 23:59:59' );

            $monthly = $wpdb->get_results( $wpdb->prepare(
                "SELECT pm_type.meta_value AS type, pm_amount.meta_value AS amount
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm_type   ON p.ID = pm_type.post_id   AND pm_type.meta_key   = '_aura_transaction_type'
                 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key  = '_aura_transaction_status'
                 INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key  = '_aura_transaction_amount'
                 WHERE p.post_type = 'aura_transaction' AND p.post_status = 'publish'
                   AND pm_status.meta_value = 'approved'
                   AND p.post_date BETWEEN %s AND %s",
                $month_start, $month_end
            ) );

            $income = $expense = 0;
            foreach ( $monthly as $row ) {
                $amt = floatval( $row->amount );
                if ( $row->type === 'income' ) { $income += $amt; } else { $expense += $amt; }
            }

            $pending = current_user_can( 'aura_finance_approve' )
                ? (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                     WHERE p.post_type = 'aura_transaction' AND p.post_status = 'publish'
                       AND pm.meta_key = '_aura_transaction_status' AND pm.meta_value = 'pending'"
                ) : 0;

            $total_budget = (float) get_option( 'aura_annual_budget', 0 );
            $budget_exec  = $total_budget > 0 ? min( 100, round( ( $expense / $total_budget ) * 100, 1 ) ) : 0;

            $data['finance'] = [
                'income'      => $income,
                'expense'     => $expense,
                'pending'     => $pending,
                'budget_exec' => $budget_exec,
            ];
            $data['pending_approvals'] = $pending;
        }

        // Vehículos
        if ( Aura_Roles_Manager::user_can_view_module( 'vehicles' ) ) {
            $today_start = date( 'Y-m-d 00:00:00' );
            $today_end   = date( 'Y-m-d 23:59:59' );
            $today = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'aura_vehicle_exit'
                 AND post_status = 'publish' AND post_date BETWEEN %s AND %s",
                $today_start, $today_end
            ) );

            $critical = 0;
            if ( class_exists( 'Aura_Vehicle_Alerts' ) && current_user_can( 'aura_vehicles_alerts' ) ) {
                $alerts   = Aura_Vehicle_Alerts::get_vehicles_needing_attention();
                $critical = count( array_filter( $alerts, function ( $a ) { return $a['urgency'] === 'critical'; } ) );
            }

            $data['vehicles'] = [ 'today' => $today, 'critical' => $critical ];
        }

        wp_send_json_success( $data );
    }

    public function enqueue_admin_assets($hook) {
        // CSS global del admin con anti-caché dinámico
        $admin_css_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/css/admin-styles.css');
        wp_enqueue_style(
            'aura-admin-styles',
            AURA_PLUGIN_URL . 'assets/css/admin-styles.css',
            array(),
            $admin_css_ver
        );

        // CSS de notificaciones (campana en admin bar) — Fase 5, Item 5.4
        wp_enqueue_style(
            'aura-notifications',
            AURA_PLUGIN_URL . 'assets/css/notifications.css',
            array(),
            AURA_VERSION
        );

        // ── Dashboard Principal (toplevel_page_aura-suite) ──────────────────────
        if ( $hook === 'toplevel_page_aura-suite' ) {
            wp_enqueue_script(
                'aura-main-dashboard',
                AURA_PLUGIN_URL . 'assets/js/main-dashboard.js',
                array( 'jquery' ),
                AURA_VERSION,
                true
            );
            wp_localize_script( 'aura-main-dashboard', 'auraVars', array(
                'nonce'   => wp_create_nonce( 'aura_dashboard_nonce' ),
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
            ) );
        }

        if ( strpos( $hook, '_page_aura-financial-analytics' ) !== false ) {
            // ApexCharts desde CDN
            wp_enqueue_script(
                'apexcharts',
                'https://cdn.jsdelivr.net/npm/apexcharts@3.46.0/dist/apexcharts.min.js',
                array(),
                '3.46.0',
                true
            );
            wp_enqueue_style(
                'aura-financial-analytics',
                AURA_PLUGIN_URL . 'assets/css/financial-analytics.css',
                array( 'aura-admin-styles' ),
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-financial-analytics',
                AURA_PLUGIN_URL . 'assets/js/financial-charts-advanced.js',
                array( 'jquery', 'apexcharts' ),
                AURA_VERSION,
                true
            );
            wp_localize_script( 'aura-financial-analytics', 'auraAnalytics', array(
                'ajaxurl'          => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'aura_analytics_nonce' ),
                'currency_symbol'  => get_option( 'aura_currency_symbol', '$' ),
                'transactions_url' => admin_url( 'admin.php?page=aura-financial-transactions' ),
                'txt' => array(
                    'loading'          => __( 'Cargando…', 'aura-suite' ),
                    'error'            => __( 'Error al cargar datos.', 'aura-suite' ),
                    'no_data'          => __( 'Sin datos para el período seleccionado.', 'aura-suite' ),
                    'no_outliers'      => __( 'No se detectaron transacciones atípicas.', 'aura-suite' ),
                    'income'           => __( 'Ingresos', 'aura-suite' ),
                    'expense'          => __( 'Egresos', 'aura-suite' ),
                    'balance'          => __( 'Balance', 'aura-suite' ),
                    'projection'       => __( 'Proyección', 'aura-suite' ),
                    'transactions'     => __( 'Transacciones', 'aura-suite' ),
                    'avg_amount'       => __( 'Monto promedio', 'aura-suite' ),
                    'budget'           => __( 'Presupuesto', 'aura-suite' ),
                    'actual'           => __( 'Ejecutado', 'aura-suite' ),
                    'over_budget'      => __( 'Excedido', 'aura-suite' ),
                    'on_track'         => __( 'En línea', 'aura-suite' ),
                    'see_detail'       => __( 'Ver detalles', 'aura-suite' ),
                    'annotations'      => __( 'Anotaciones', 'aura-suite' ),
                    'annotation_saved' => __( 'Anotación guardada', 'aura-suite' ),
                    'saved'            => __( 'Presupuestos guardados', 'aura-suite' ),
                    'confirm_delete'   => __( '¿Eliminar esta anotación?', 'aura-suite' ),
                ),
            ) );
        }

        // Assets para Reportes Financieros (Fase 3, Item 3.2)
        if ( strpos( $hook, '_page_aura-financial-reports' ) !== false ) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
                array(),
                '4.4.4',
                true
            );
            wp_enqueue_style(
                'aura-financial-reports',
                AURA_PLUGIN_URL . 'assets/css/financial-reports.css',
                array( 'aura-admin-styles' ),
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-financial-reports',
                AURA_PLUGIN_URL . 'assets/js/financial-reports.js',
                array( 'jquery', 'chartjs' ),
                AURA_VERSION,
                true
            );
            wp_localize_script( 'aura-financial-reports', 'auraReports', array(
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'aura_reports_nonce' ),
                'exportNonce' => wp_create_nonce( 'aura_reports_export' ),
            ) );
        }

        // Assets para Mi Dashboard Financiero Personal (Fase 6, Item 6.2)
        if ( strpos( $hook, '_page_aura-my-finance' ) !== false ) {
            // jQuery UI Autocomplete para selector de usuario
            wp_enqueue_script( 'jquery-ui-autocomplete' );
            wp_enqueue_style( 'jquery-ui-autocomplete-style',
                'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css',
                [], '1.13.2'
            );
            wp_enqueue_style(
                'aura-financial-user-dashboard-css',
                AURA_PLUGIN_URL . 'assets/css/financial-user-dashboard.css',
                array(),
                AURA_VERSION . '.' . @filemtime( AURA_PLUGIN_DIR . 'assets/css/financial-user-dashboard.css' )
            );
        }

        // Assets para Libro Mayor por Usuario (Fase 6, Item 6.3)
        if ( strpos( $hook, '_page_aura-user-ledger' ) !== false ) {
            // jQuery UI Autocomplete para selector de usuario
            wp_enqueue_script( 'jquery-ui-autocomplete' );
            wp_enqueue_style( 'jquery-ui-autocomplete-style-ledger',
                'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css',
                [], '1.13.2'
            );
        }

        // Assets para el Dashboard Financiero (Fase 3, Item 3.1)
        if ( $hook === 'toplevel_page_aura-financial-dashboard' ) {            // Chart.js 4.x desde CDN
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
                array(),
                '4.4.4',
                true
            );

            // CSS del dashboard financiero
            wp_enqueue_style(
                'aura-financial-dashboard',
                AURA_PLUGIN_URL . 'assets/css/financial-dashboard.css',
                array( 'aura-admin-styles' ),
                AURA_VERSION
            );

            // JS del dashboard financiero
            wp_enqueue_script(
                'aura-financial-dashboard',
                AURA_PLUGIN_URL . 'assets/js/financial-dashboard.js',
                array( 'jquery', 'chartjs', 'aura-ui-core' ),
                AURA_VERSION,
                true
            );

            wp_localize_script( 'aura-financial-dashboard', 'auraDashboard', array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'aura_dashboard_nonce' ),
                'txListUrl' => admin_url( 'admin.php?page=aura-financial-transactions' ),
                'i18n'      => array(
                    'income'      => __( 'Ingresos', 'aura-suite' ),
                    'expense'     => __( 'Egresos', 'aura-suite' ),
                    'prevIncome'  => __( 'Ingresos (período ant.)', 'aura-suite' ),
                    'prevExpense' => __( 'Egresos (período ant.)', 'aura-suite' ),
                    'refreshing'  => __( 'Actualizando…', 'aura-suite' ),
                    'selectDates' => __( 'Selecciona fecha de inicio y fin.', 'aura-suite' ),
                ),
            ) );

            // Widget presupuestos en dashboard (Fase 5, Item 5.1)
            wp_enqueue_style( 'aura-budgets', AURA_PLUGIN_URL . 'assets/css/budgets.css', array( 'aura-admin-styles' ), AURA_VERSION );
            wp_enqueue_script( 'aura-budgets', AURA_PLUGIN_URL . 'assets/js/budgets.js', array( 'jquery' ), AURA_VERSION, true );
            wp_localize_script( 'aura-budgets', 'auraBudgets', array(
                'ajaxurl'    => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( 'aura_budgets_nonce' ),
                'budgetsUrl' => admin_url( 'admin.php?page=aura-financial-budgets' ),
                'txt'        => array(
                    'no_active_budgets' => __( 'Sin presupuestos activos en el período actual.', 'aura-suite' ),
                    'executed'          => __( 'Ejecutado', 'aura-suite' ),
                    'budget_total'      => __( 'Presupuesto', 'aura-suite' ),
                    'available'         => __( 'Disponible', 'aura-suite' ),
                    'overrun'           => __( 'Exceso', 'aura-suite' ),
                    'projection'        => __( 'Proyección al fin del período', 'aura-suite' ),
                    'total_budget'       => __( 'Total presupuestado', 'aura-suite' ),
                    'total_executed'     => __( 'Total ejecutado', 'aura-suite' ),
                    'total_budgets'      => __( 'Presupuestos', 'aura-suite' ),
                    'overrun_count'      => __( 'Sobrepasados', 'aura-suite' ),
                    'critical_count'     => __( 'En alerta', 'aura-suite' ),
                    'ok_count'           => __( 'En buen estado', 'aura-suite' ),
                    'new_title'          => __( 'Nuevo Presupuesto', 'aura-suite' ),
                    'edit_title'         => __( 'Editar Presupuesto', 'aura-suite' ),
                    'detail_title'       => __( 'Detalle de Presupuesto', 'aura-suite' ),
                    'confirm_delete'     => __( '¿Eliminar este presupuesto?', 'aura-suite' ),
                    'error_generic'      => __( 'Error al procesar la solicitud.', 'aura-suite' ),
                    'loading'            => __( 'Cargando…', 'aura-suite' ),
                    'created'            => __( 'Presupuesto creado.', 'aura-suite' ),
                    'updated'            => __( 'Presupuesto actualizado.', 'aura-suite' ),
                    'deleted'            => __( 'Presupuesto eliminado.', 'aura-suite' ),
                    'adjusted'           => __( 'Nuevo monto', 'aura-suite' ),
                    'adjust_invalid'     => __( 'Ingresa un valor distinto de cero.', 'aura-suite' ),
                    'no_history'         => __( 'Sin historial de períodos anteriores.', 'aura-suite' ),
                    'no_transactions'    => __( 'Sin transacciones en este período.', 'aura-suite' ),
                    'total'              => __( 'Total', 'aura-suite' ),
                    'monthly'            => __( 'Mensual', 'aura-suite' ),
                    'quarterly'          => __( 'Trimestral', 'aura-suite' ),
                    'semestral'          => __( 'Semestral', 'aura-suite' ),
                    'yearly'             => __( 'Anual', 'aura-suite' ),
                    'detail'             => __( 'Ver detalle', 'aura-suite' ),
                    'edit'               => __( 'Editar', 'aura-suite' ),
                    'delete'             => __( 'Eliminar', 'aura-suite' ),
                    'h_category'         => __( 'Categoría', 'aura-suite' ),
                    'h_period'           => __( 'Período', 'aura-suite' ),
                    'h_budget'           => __( 'Presupuesto', 'aura-suite' ),
                    'h_executed'         => __( 'Ejecutado', 'aura-suite' ),
                    'h_available'        => __( 'Disponible', 'aura-suite' ),
                    'h_pct'              => __( '%', 'aura-suite' ),
                    'h_progress'         => __( 'Progreso', 'aura-suite' ),
                    'h_actions'          => __( 'Acciones', 'aura-suite' ),
                    'h_area'             => __( 'Área/Programa', 'aura-suite' ),
                    'no_area'            => __( 'General', 'aura-suite' ),
                ),
            ) );
        }

        // Cargar scripts de transacciones en el dashboard principal para el widget de aprobaciones
        if ($hook === 'index.php' && current_user_can('aura_finance_approve')) {
            $tx_list_js_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/js/transactions-list.js');

            wp_enqueue_script(
                'aura-transactions-list',
                AURA_PLUGIN_URL . 'assets/js/transactions-list.js',
                array('jquery'),
                $tx_list_js_ver,
                true
            );
            
            wp_localize_script('aura-transactions-list', 'auraTransactionsList', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('aura_transactions_list_nonce'),
                'messages' => array(
                    'confirmApprove' => __('¿Aprobar esta transacción?', 'aura-suite'),
                    'confirmReject' => __('¿Rechazar esta transacción?', 'aura-suite'),
                    'rejectReason' => __('Motivo del rechazo:', 'aura-suite'),
                )
            ));
        }
        
        // Assets específicos para la página de listado de transacciones
        if (strpos($hook, '_page_aura-financial-transactions') !== false) {
            // Autocomplete para filtro de usuario vinculado
            wp_enqueue_script('jquery-ui-autocomplete');

            // CSS y JS del modal de exportación (Fase 4, Item 4.1)
            wp_enqueue_style(
                'aura-export-modal',
                AURA_PLUGIN_URL . 'assets/css/export-modal.css',
                array( 'aura-admin-styles' ),
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-export-modal',
                AURA_PLUGIN_URL . 'assets/js/export-modal.js',
                array( 'jquery' ),
                AURA_VERSION,
                true
            );
            wp_localize_script( 'aura-export-modal', 'auraExport', array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'aura_export_nonce' ),
                'txt'     => array(
                    'export_btn'     => __( 'Exportar', 'aura-suite' ),
                    'generating'     => __( 'Generando…', 'aura-suite' ),
                    'error_generic'  => __( 'Error al generar el archivo. Intente de nuevo.', 'aura-suite' ),
                    'no_columns'     => __( 'Seleccione al menos una columna.', 'aura-suite' ),
                    'no_selection'   => __( 'No hay transacciones seleccionadas en la tabla.', 'aura-suite' ),
                    'use_filters'    => __( 'Usar filtros actuales (%d transacciones)', 'aura-suite' ),
                    'selected_count' => __( '%d transacciones seleccionadas', 'aura-suite' ),
                    'success'        => __( 'Archivo "%s" generado exitosamente.', 'aura-suite' ),
                ),
            ) );

            // jQuery UI y DatePicker
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-datepicker-style', 
                'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css'
            );
            
            // Select2 para filtros avanzados
            wp_enqueue_style('select2', 
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css'
            );
            wp_enqueue_script('select2', 
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                array('jquery'),
                '4.1.0',
                true
            );
            
            // CSS del listado de transacciones
            // Depende de aura-admin-styles para garantizar que admin-styles.css
            // se cargue ANTES (los overrides de BFC/tablenav deben tener precedencia).
            wp_enqueue_style(
                'aura-transactions-list',
                AURA_PLUGIN_URL . 'assets/css/transactions-list.css',
                array( 'aura-admin-styles' ),
                AURA_VERSION
            );
            
            // JS del listado de transacciones
            $tx_list_js_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/js/transactions-list.js');

            wp_enqueue_script(
                'aura-transactions-list',
                AURA_PLUGIN_URL . 'assets/js/transactions-list.js',
                array('jquery', 'jquery-ui-datepicker', 'jquery-ui-autocomplete', 'select2'),
                $tx_list_js_ver,
                true
            );
            
            // Localizar script con datos para AJAX
            wp_localize_script('aura-transactions-list', 'auraTransactionsList', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('aura_transactions_list_nonce'),
                'transactionNonce' => wp_create_nonce('aura_transaction_nonce'),
                'messages' => array(
                    'confirmApprove' => __('¿Aprobar esta transacción?', 'aura-suite'),
                    'confirmReject' => __('¿Rechazar esta transacción?', 'aura-suite'),
                    'rejectReason' => __('Motivo del rechazo:', 'aura-suite'),
                    'confirmBulkDelete' => __('¿Eliminar las transacciones seleccionadas?', 'aura-suite'),
                    'filterSaved' => __('Filtro guardado correctamente', 'aura-suite'),
                    'filterLoaded' => __('Filtro cargado correctamente', 'aura-suite'),
                    'error' => __('Error al procesar la solicitud', 'aura-suite'),
                ),
            ));
            
            // CSS del modal de transacciones
            wp_enqueue_style(
                'aura-transaction-modal',
                AURA_PLUGIN_URL . 'assets/css/transaction-modal.css',
                array('aura-transactions-list'),
                AURA_VERSION
            );
            
            // JS del modal de transacciones
            wp_enqueue_script(
                'aura-transaction-modal',
                AURA_PLUGIN_URL . 'assets/js/transaction-modal.js',
                array('jquery', 'aura-transactions-list'),
                AURA_VERSION,
                true
            );
            
            // Localizar script del modal con datos para AJAX
            wp_localize_script('aura-transaction-modal', 'auraTransactionModal', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aura_transaction_modal_nonce'),
                'currentUserId' => get_current_user_id(),
                'permissions' => array(
                    'canCreate' => current_user_can('aura_finance_create'),
                    'canEditOwn' => current_user_can('aura_finance_edit_own'),
                    'canEditAll' => current_user_can('aura_finance_edit_all'),
                    'canDeleteOwn' => current_user_can('aura_finance_delete_own'),
                    'canDeleteAll' => current_user_can('aura_finance_delete_all'),
                    'canApprove' => current_user_can('aura_finance_approve'),
                    'canViewOwn' => current_user_can('aura_finance_view_own'),
                    'canViewAll' => current_user_can('aura_finance_view_all')
                ),
                'messages' => array(
                    'confirmApprove' => __('¿Aprobar esta transacción?', 'aura-suite'),
                    'confirmReject' => __('¿Rechazar esta transacción? Debes proporcionar una razón.', 'aura-suite'),
                    'confirmDelete' => __('¿Eliminar esta transacción? Esta acción no se puede deshacer.', 'aura-suite'),
                    'error' => __('Error al procesar la solicitud', 'aura-suite'),
                    'statusPending' => __('Pendiente', 'aura-suite'),
                    'statusApproved' => __('Aprobado', 'aura-suite'),
                    'statusRejected' => __('Rechazado', 'aura-suite'),
                    'typeIncome' => __('Ingreso', 'aura-suite'),
                    'typeExpense' => __('Egreso', 'aura-suite'),
                    'loading' => __('Cargando...', 'aura-suite'),
                    'noReceipt' => __('Sin comprobante', 'aura-suite'),
                    'noNotes' => __('Sin notas', 'aura-suite'),
                    'noHistory' => __('Sin cambios registrados', 'aura-suite'),
                    'downloadReceipt' => __('Descargar Comprobante', 'aura-suite'),
                    'viewReceipt' => __('Ver Comprobante', 'aura-suite')
                ),
                'editUrl' => admin_url('admin.php?page=aura-financial-edit-transaction'),
                'newUrl' => admin_url('admin.php?page=aura-financial-new-transaction'),
                'exportUrl' => admin_url('admin-ajax.php?action=aura_export_transaction_pdf')
            ));
        }
        
        // Assets específicos para la página de importación (Fase 4, Item 4.2)
        if (strpos($hook, '_page_aura-financial-import') !== false) {
            wp_enqueue_style(
                'aura-import-wizard',
                AURA_PLUGIN_URL . 'assets/css/import-wizard.css',
                array('aura-admin-styles'),
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-import-wizard',
                AURA_PLUGIN_URL . 'assets/js/import-wizard.js',
                array('jquery'),
                AURA_VERSION,
                true
            );
            wp_localize_script('aura-import-wizard', 'auraImport', array(
                'ajaxurl'          => admin_url('admin-ajax.php'),
                'nonce'            => wp_create_nonce('aura_import_nonce'),
                'importTypeLabels' => array(
                    'transactions'  => __('transacciones', 'aura-suite'),
                    'accounts'      => __('cuentas', 'aura-suite'),
                    'categories'    => __('categorías', 'aura-suite'),
                    'areas'         => __('áreas y programas', 'aura-suite'),
                    'third_parties' => __('directorio de terceros', 'aura-suite'),
                ),
                'viewByType' => array(
                    'transactions' => array(
                        'url'   => admin_url( 'admin.php?page=aura-financial-transactions' ),
                        'label' => __( 'Ver transacciones importadas', 'aura-suite' ),
                    ),
                    'accounts' => array(
                        'url'   => admin_url( 'admin.php?page=aura-financial-accounts' ),
                        'label' => __( 'Ver cuentas importadas', 'aura-suite' ),
                    ),
                    'categories' => array(
                        'url'   => admin_url( 'admin.php?page=aura-financial-categories' ),
                        'label' => __( 'Ver categorías importadas', 'aura-suite' ),
                    ),
                    'areas' => array(
                        'url'   => admin_url( 'admin.php?page=aura-areas' ),
                        'label' => __( 'Ver áreas y programas', 'aura-suite' ),
                    ),
                    'third_parties' => array(
                        'url'   => admin_url( 'admin.php?page=aura-third-parties' ),
                        'label' => __( 'Ver directorio de terceros', 'aura-suite' ),
                    ),
                ),
                'fieldsByType' => array(
                    'transactions' => array(
                        // Grupo: Obligatorios
                        array('key' => 'transaction_date', 'label' => __('Fecha *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-calendar-alt', 'desc' => __('Fecha de la transacción (ej: YYYY-MM-DD o DD/MM/YYYY)', 'aura-suite')),
                        array('key' => 'transaction_type', 'label' => __('Tipo de movimiento *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-randomize', 'desc' => __('Ingreso, Egreso o Capital (CapEx)', 'aura-suite')),
                        array('key' => 'category_id', 'label' => __('Categoría (Nombre) *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-category', 'desc' => __('Nombre en texto de la categoría. Se comparará o creará en el paso 3', 'aura-suite')),
                        array('key' => 'amount', 'label' => __('Monto *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-money-alt', 'desc' => __('Importe numérico de la transacción', 'aura-suite')),

                        // Grupo: Usuarios y Aprobación
                        array('key' => 'created_by', 'label' => __('Creado por (Usuario WP)', 'aura-suite'), 'required' => false, 'group' => 'users', 'icon' => 'dashicons-admin-users', 'desc' => __('Nombre o display_name de quien registró el movimiento', 'aura-suite')),
                        array('key' => 'approved_by', 'label' => __('Aprobado por (Usuario WP)', 'aura-suite'), 'required' => false, 'group' => 'users', 'icon' => 'dashicons-yes-alt', 'desc' => __('Nombre o display_name del usuario aprobador', 'aura-suite')),
                        array('key' => 'status', 'label' => __('Estado (pending/approved)', 'aura-suite'), 'required' => false, 'group' => 'users', 'icon' => 'dashicons-flag', 'desc' => __('Estado por fila (pending = pendiente, approved = aprobada)', 'aura-suite')),
                        array('key' => 'related_user_id', 'label' => __('Usuario Vinculado', 'aura-suite'), 'required' => false, 'group' => 'users', 'icon' => 'dashicons-businessman', 'desc' => __('Cliente, empleado o contacto relacionado (nombre o email)', 'aura-suite')),

                        // Grupo: Cuentas y Pago
                        array('key' => 'payment_method', 'label' => __('Método de pago', 'aura-suite'), 'required' => false, 'group' => 'payment', 'icon' => 'dashicons-cart', 'desc' => __('Efectivo, Transferencia, Tarjeta, etc.', 'aura-suite')),
                        array('key' => 'reference_number', 'label' => __('N° Referencia', 'aura-suite'), 'required' => false, 'group' => 'payment', 'icon' => 'dashicons-tag', 'desc' => __('Código de comprobante o referencia bancaria', 'aura-suite')),
                        array('key' => 'source_account_id', 'label' => __('Cuenta origen', 'aura-suite'), 'required' => false, 'group' => 'payment', 'icon' => 'dashicons-bank', 'desc' => __('Nombre o ID de la cuenta bancaria emisora', 'aura-suite')),
                        array('key' => 'destination_account_id', 'label' => __('Cuenta destino', 'aura-suite'), 'required' => false, 'group' => 'payment', 'icon' => 'dashicons-building', 'desc' => __('Nombre o ID de la cuenta receptora (transferencias)', 'aura-suite')),

                        // Grupo: Detalles y Clasificación
                        array('key' => 'description', 'label' => __('Descripción / Concepto', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-editor-alignleft', 'desc' => __('Detalle o concepto (por defecto "Importado")', 'aura-suite')),
                        array('key' => 'recipient_payer', 'label' => __('Beneficiario / Pagador', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-id-alt', 'desc' => __('Persona física o empresa receptora / emisora', 'aura-suite')),
                        array('key' => 'area_id', 'label' => __('Área / Programa', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-networking', 'desc' => __('Nombre o ID del área o programa contable', 'aura-suite')),
                        array('key' => 'receipt_file', 'label' => __('Comprobante / Archivo adjunto', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-media-default', 'desc' => __('Nombre del archivo adjunto (en ZIP) o URL remota', 'aura-suite')),
                        array('key' => 'notes', 'label' => __('Notas adicionales', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-welcome-write-blog', 'desc' => __('Observaciones, etiquetas o texto adicional', 'aura-suite')),
                        array('key' => 'tags', 'label' => __('Etiquetas', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-tagcloud', 'desc' => __('Etiquetas clasificatorias separadas por comas', 'aura-suite')),
                    ),
                    'accounts' => array(
                        array('key' => 'name', 'label' => __('Nombre de cuenta *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-bank', 'desc' => __('Nombre identificador de la cuenta', 'aura-suite')),
                        array('key' => 'account_type', 'label' => __('Tipo de cuenta *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-category', 'desc' => __('bank, cash, credit, etc.', 'aura-suite')),
                        array('key' => 'currency', 'label' => __('Moneda *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-money-alt', 'desc' => __('Código ISO (ej: USD, EUR, COP)', 'aura-suite')),
                        array('key' => 'initial_balance', 'label' => __('Saldo inicial *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-chart-line', 'desc' => __('Saldo numérico de apertura', 'aura-suite')),
                        array('key' => 'is_active', 'label' => __('Activa (1/0) *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-yes', 'desc' => __('1 para activa, 0 para inactiva', 'aura-suite')),
                        array('key' => 'institution', 'label' => __('Institución', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-building', 'desc' => __('Nombre de la entidad o banco', 'aura-suite')),
                        array('key' => 'account_number_masked', 'label' => __('Número enmascarado', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-lock', 'desc' => __('Ej: **** 1234', 'aura-suite')),
                        array('key' => 'meta_json', 'label' => __('Meta JSON', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-code-standards', 'desc' => __('Metadatos adicionales en formato JSON', 'aura-suite')),
                    ),
                    'categories' => array(
                        array('key' => 'name', 'label' => __('Nombre *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-category', 'desc' => __('Nombre visible de la categoría', 'aura-suite')),
                        array('key' => 'slug', 'label' => __('Slug *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-admin-links', 'desc' => __('Identificador único (texto en minúsculas sin espacios)', 'aura-suite')),
                        array('key' => 'type', 'label' => __('Tipo *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-randomize', 'desc' => __('income, expense, capital o both', 'aura-suite')),
                        array('key' => 'color', 'label' => __('Color hex *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-art', 'desc' => __('Código hexadecimal (ej: #2271b1)', 'aura-suite')),
                        array('key' => 'icon', 'label' => __('Icono dashicon *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-format-image', 'desc' => __('Nombre de icono (ej: dashicons-category)', 'aura-suite')),
                        array('key' => 'is_active', 'label' => __('Activa (1/0) *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-yes', 'desc' => __('1 para activa, 0 para inactiva', 'aura-suite')),
                        array('key' => 'display_order', 'label' => __('Orden *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-menu', 'desc' => __('Número entero de orden de visualización', 'aura-suite')),
                        array('key' => 'parent_category_slug', 'label' => __('Slug categoría padre', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-networking', 'desc' => __('Slug de la categoría superior en caso de ser subcategoría', 'aura-suite')),
                        array('key' => 'description', 'label' => __('Descripción', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-editor-alignleft', 'desc' => __('Descripción breve del uso de la categoría', 'aura-suite')),
                    ),
                    'areas' => array(
                        array('key' => 'name', 'label' => __('Nombre de área *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-networking', 'desc' => __('Nombre del departamento o programa', 'aura-suite')),
                        array('key' => 'slug', 'label' => __('Slug', 'aura-suite'), 'required' => false, 'group' => 'required', 'icon' => 'dashicons-admin-links', 'desc' => __('Identificador único (se autogenera si se deja vacío)', 'aura-suite')),
                        array('key' => 'type', 'label' => __('Tipo *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-tag', 'desc' => __('department, program o team', 'aura-suite')),
                        array('key' => 'parent_area_slug', 'label' => __('Slug Área Padre', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-category', 'desc' => __('Slug del área superior si es sub-área', 'aura-suite')),
                        array('key' => 'color', 'label' => __('Color Hex', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-art', 'desc' => __('Color distintivo (ej: #4A90E2)', 'aura-suite')),
                        array('key' => 'icon', 'label' => __('Icono Dashicon', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-format-image', 'desc' => __('Ej: dashicons-category', 'aura-suite')),
                        array('key' => 'status', 'label' => __('Estado', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-yes', 'desc' => __('active o archived', 'aura-suite')),
                        array('key' => 'sort_order', 'label' => __('Orden', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-menu', 'desc' => __('Orden numérico de presentación', 'aura-suite')),
                        array('key' => 'responsible_user', 'label' => __('Usuario Responsable', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-admin-users', 'desc' => __('Username o email del líder responsable', 'aura-suite')),
                        array('key' => 'description', 'label' => __('Descripción', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-editor-alignleft', 'desc' => __('Detalle de actividades del área', 'aura-suite')),
                    ),
                    'third_parties' => array(
                        array('key' => 'full_name', 'label' => __('Razón Social / Nombre *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-building', 'desc' => __('Nombre legal o razón social completa', 'aura-suite')),
                        array('key' => 'party_type', 'label' => __('Tipo de Entidad *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-groups', 'desc' => __('company, store, organization_foundation, person, religious, other', 'aura-suite')),
                        array('key' => 'accounting_role', 'label' => __('Rol Contable *', 'aura-suite'), 'required' => true, 'group' => 'required', 'icon' => 'dashicons-money-alt', 'desc' => __('supplier, customer, employee, bank_creditor, tax_authority, other', 'aura-suite')),
                        array('key' => 'commercial_name', 'label' => __('Nombre Comercial', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-tag', 'desc' => __('Nombre de fantasía o marca comercial', 'aura-suite')),
                        array('key' => 'document_id', 'label' => __('N° Documento / NIT', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-id-alt', 'desc' => __('Identificación tributaria o documento de identidad', 'aura-suite')),
                        array('key' => 'tax_id_type', 'label' => __('Tipo Identificación', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-clipboard', 'desc' => __('NIT, CC, RUT, Pasaporte, etc.', 'aura-suite')),
                        array('key' => 'phone', 'label' => __('Teléfono', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-phone', 'desc' => __('Teléfono fijo o móvil con código de país', 'aura-suite')),
                        array('key' => 'email', 'label' => __('Correo Electrónico', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-email', 'desc' => __('Email corporativo o de contacto', 'aura-suite')),
                        array('key' => 'website', 'label' => __('Sitio Web', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-admin-site-alt3', 'desc' => __('URL de sitio web institucional', 'aura-suite')),
                        array('key' => 'address', 'label' => __('Dirección Física', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-location', 'desc' => __('Ubicación, ciudad o dirección fiscal', 'aura-suite')),
                        array('key' => 'notes', 'label' => __('Notas / Observaciones', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-welcome-write-blog', 'desc' => __('Observaciones sobre condiciones o acuerdos comerciales', 'aura-suite')),
                        array('key' => 'is_active', 'label' => __('Activo (1/0)', 'aura-suite'), 'required' => false, 'group' => 'details', 'icon' => 'dashicons-yes', 'desc' => __('1 para activo, 0 para inactivo', 'aura-suite')),
                    ),
                ),
                'txt' => array(
                    'error_generic'   => __('Error al procesar la solicitud. Intente de nuevo.', 'aura-suite'),
                    'validating'      => __('Validando', 'aura-suite'),
                    'validate_btn'    => __('Validar datos', 'aura-suite'),
                    'map_required'    => __('Faltan campos obligatorios para este tipo de importación.', 'aura-suite'),
                    'importing'       => __('Importando registros…', 'aura-suite'),
                    'no_valid'        => __('No hay filas válidas para importar', 'aura-suite'),
                    'file_label'      => __('Archivo', 'aura-suite'),
                    'rows_label'      => __('Total filas', 'aura-suite'),
                    'row'             => __('Fila', 'aura-suite'),
                    'stat_total'      => __('Total filas', 'aura-suite'),
                    'stat_valid'      => __('Válidas', 'aura-suite'),
                    'stat_invalid'    => __('Con errores', 'aura-suite'),
                    'stat_warnings'   => __('Advertencias', 'aura-suite'),
                    'stat_imported'   => __('Importadas', 'aura-suite'),
                    'stat_failed'     => __('Fallidas', 'aura-suite'),
                    'ready_to_import' => __('Se importarán %d %s válidas.', 'aura-suite'),
                    'import_n'        => __('Importar %d %s', 'aura-suite'),
                    'default_record_label' => __('registros', 'aura-suite'),
                    'confirm_rollback'=> __('¿Deshacer esta importación? Se revertirán los registros creados por este lote.', 'aura-suite'),
                    'rollback_done'   => __('%d registros revertidos.', 'aura-suite'),
                    'no_history'      => __('Sin importaciones registradas.', 'aura-suite'),
                    'h_date'          => __('Fecha', 'aura-suite'),
                    'h_file'          => __('Archivo', 'aura-suite'),
                    'h_total'         => __('Total', 'aura-suite'),
                    'h_imported'      => __('Importadas', 'aura-suite'),
                    'h_failed'        => __('Fallidas', 'aura-suite'),
                    'h_status'        => __('Estado', 'aura-suite'),
                    'h_actions'       => __('Acciones', 'aura-suite'),
                    'completed'       => __('Completado', 'aura-suite'),
                    'rolled_back'     => __('Revertido', 'aura-suite'),
                    'undo'            => __('Deshacer', 'aura-suite'),
                    'rollback_expired'=> __('Venció 1 mes', 'aura-suite'),
                    'auto_cat_note'   => __('Las categorías marcadas con ⚠ se crearán automáticamente si tiene esa opción habilitada.', 'aura-suite'),
                    'create_auto'     => __('+ Crear automáticamente en BD', 'aura-suite'),
                    'found_in_db'     => __('Existe en BD', 'aura-suite'),
                    'not_in_db'       => __('No existe en BD', 'aura-suite'),
                    'matched_user'    => __('Usuario WP detectado', 'aura-suite'),
                    'unmatched_user'  => __('Sin coincidencia exacta', 'aura-suite'),
                ),
            ));
        }

        // Assets específicos para presupuestos (Fase 5, Item 5.1)
        if (strpos($hook, '_page_aura-financial-budgets') !== false) {
            wp_enqueue_script(
                'apexcharts',
                'https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js',
                array(),
                '3.44.0',
                true
            );
            wp_enqueue_style(
                'aura-budgets',
                AURA_PLUGIN_URL . 'assets/css/budgets.css',
                array('aura-admin-styles'),
                AURA_VERSION . '.' . (file_exists(AURA_PLUGIN_DIR . 'assets/css/budgets.css') ? filemtime(AURA_PLUGIN_DIR . 'assets/css/budgets.css') : time())
            );
            wp_enqueue_script(
                'aura-budgets',
                AURA_PLUGIN_URL . 'assets/js/budgets.js',
                array('jquery', 'apexcharts'),
                AURA_VERSION . '.' . (file_exists(AURA_PLUGIN_DIR . 'assets/js/budgets.js') ? filemtime(AURA_PLUGIN_DIR . 'assets/js/budgets.js') : time()),
                true
            );
            wp_localize_script('aura-budgets', 'auraBudgets', array(
                'ajaxurl'     => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('aura_budgets_nonce'),
                'budgetsUrl'  => admin_url('admin.php?page=aura-financial-budgets'),
                'txt' => array(
                    'new_title'          => __('Nuevo Presupuesto', 'aura-suite'),
                    'edit_title'         => __('Editar Presupuesto', 'aura-suite'),
                    'detail_title'       => __('Detalle de Presupuesto', 'aura-suite'),
                    'confirm_delete'     => __('¿Eliminar este presupuesto? Esta acción no se puede deshacer.', 'aura-suite'),
                    'error_generic'      => __('Error al procesar la solicitud. Intente de nuevo.', 'aura-suite'),
                    'loading'            => __('Cargando…', 'aura-suite'),
                    'created'            => __('Presupuesto creado correctamente.', 'aura-suite'),
                    'updated'            => __('Presupuesto actualizado correctamente.', 'aura-suite'),
                    'deleted'            => __('Presupuesto eliminado.', 'aura-suite'),
                    'adjusted'           => __('Presupuesto ajustado. Nuevo monto', 'aura-suite'),
                    'adjust_invalid'     => __('Ingresa un valor de ajuste distinto de cero.', 'aura-suite'),
                    'no_history'         => __('No hay historial de períodos anteriores.', 'aura-suite'),
                    'no_transactions'    => __('Sin transacciones en este período.', 'aura-suite'),
                    'no_active_budgets'  => __('Sin presupuestos activos en el período actual.', 'aura-suite'),
                    'total'              => __('Total', 'aura-suite'),
                    'total_budget'       => __('Total presupuestado', 'aura-suite'),
                    'total_executed'     => __('Total ejecutado', 'aura-suite'),
                    'total_budgets'      => __('Presupuestos', 'aura-suite'),
                    'overrun_count'      => __('Sobrepasados', 'aura-suite'),
                    'critical_count'     => __('En alerta', 'aura-suite'),
                    'ok_count'           => __('En buen estado', 'aura-suite'),
                    'budget_total'       => __('Presupuesto', 'aura-suite'),
                    'executed'           => __('Ejecutado', 'aura-suite'),
                    'available'          => __('Disponible', 'aura-suite'),
                    'overrun'            => __('Exceso', 'aura-suite'),
                    'projection'         => __('Proyección al fin del período', 'aura-suite'),
                    'monthly'            => __('Mensual', 'aura-suite'),
                    'quarterly'          => __('Trimestral', 'aura-suite'),
                    'semestral'          => __('Semestral', 'aura-suite'),
                    'yearly'             => __('Anual', 'aura-suite'),
                    'detail'             => __('Ver detalle', 'aura-suite'),
                    'edit'               => __('Editar', 'aura-suite'),
                    'delete'             => __('Eliminar', 'aura-suite'),
                    'h_category'         => __('Categoría', 'aura-suite'),
                    'h_period'           => __('Período', 'aura-suite'),
                    'h_budget'           => __('Presupuesto', 'aura-suite'),
                    'h_executed'         => __('Ejecutado', 'aura-suite'),
                    'h_available'        => __('Disponible', 'aura-suite'),
                    'h_pct'              => __('%', 'aura-suite'),
                    'h_progress'         => __('Progreso', 'aura-suite'),
                    'h_actions'          => __('Acciones', 'aura-suite'),
                    'h_area'             => __('Área/Programa', 'aura-suite'),
                    'area_subtotal'      => __('Subtotal', 'aura-suite'),
                    'h_tx_category'      => __('Categoría', 'aura-suite'),
                    'collapse_area'      => __('▲', 'aura-suite'),
                    'expand_area'        => __('▼', 'aura-suite'),
                    'export_excel'       => __('Exportar Excel / CSV', 'aura-suite'),
                    'export_success'     => __('Reporte de presupuestos exportado correctamente.', 'aura-suite'),
                    'search_placeholder' => __('Buscar por área o categoría…', 'aura-suite'),
                    'period_validity'    => __('Vigencia del Período', 'aura-suite'),
                    'date_start'         => __('Fecha Inicio', 'aura-suite'),
                    'date_end'           => __('Fecha Fin', 'aura-suite'),
                    'days_remaining'     => __('Días restantes', 'aura-suite'),
                    'cycle_type'         => __('Tipo de Ciclo', 'aura-suite'),
                ),
            ));
        }

        // Assets específicos para gestión de etiquetas (Fase 5, Item 5.2)
        if (strpos($hook, '_page_aura-financial-tags') !== false) {
            wp_enqueue_script('jquery-ui-autocomplete');
            $tags_css_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/css/tags-search.css');
            $tags_js_ver  = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/js/tags.js');
            wp_enqueue_style(
                'aura-tags-search',
                AURA_PLUGIN_URL . 'assets/css/tags-search.css',
                array('aura-admin-styles'),
                $tags_css_ver
            );
            wp_enqueue_script(
                'aura-tags',
                AURA_PLUGIN_URL . 'assets/js/tags.js',
                array('jquery', 'jquery-ui-autocomplete'),
                $tags_js_ver,
                true
            );
        }

        // Assets específicos para búsqueda avanzada (Fase 5, Item 5.2)
        if (strpos($hook, '_page_aura-financial-search') !== false) {
            wp_enqueue_script('jquery-ui-autocomplete');
            $search_css_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/css/tags-search.css');
            $search_js_ver  = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/js/advanced-search.js');
            wp_enqueue_style(
                'aura-tags-search',
                AURA_PLUGIN_URL . 'assets/css/tags-search.css',
                array('aura-admin-styles'),
                $search_css_ver
            );
            wp_enqueue_style(
                'aura-transaction-modal',
                AURA_PLUGIN_URL . 'assets/css/transaction-modal.css',
                array('aura-admin-styles'),
                AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/css/transaction-modal.css')
            );
            wp_enqueue_script(
                'aura-transaction-modal',
                AURA_PLUGIN_URL . 'assets/js/transaction-modal.js',
                array('jquery'),
                AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/js/transaction-modal.js'),
                true
            );
            wp_localize_script('aura-transaction-modal', 'auraTransactionModal', array(
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('aura_transaction_modal_nonce'),
                'deleteNonce' => wp_create_nonce('aura_delete_transaction_nonce'),
                'userId'    => get_current_user_id(),
                'canApprove'=> current_user_can('aura_finance_approve') || current_user_can('manage_options'),
                'canReject' => current_user_can('aura_finance_reject') || current_user_can('manage_options'),
                'canEdit'   => current_user_can('aura_finance_edit') || current_user_can('manage_options'),
                'canDelete' => current_user_can('aura_finance_delete') || current_user_can('manage_options')
            ));
            wp_enqueue_script(
                'aura-advanced-search',
                AURA_PLUGIN_URL . 'assets/js/advanced-search.js',
                array('jquery', 'jquery-ui-autocomplete', 'aura-transaction-modal'),
                $search_js_ver,
                true
            );
        }

        // Assets específicos para auditoría y trazabilidad (Fase 5, Item 5.3)
        if (strpos($hook, '_page_aura-financial-audit') !== false) {
            wp_enqueue_style(
                'aura-audit-log',
                AURA_PLUGIN_URL . 'assets/css/audit-log.css',
                array('aura-admin-styles'),
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-audit-log',
                AURA_PLUGIN_URL . 'assets/js/audit-log.js',
                array('jquery'),
                AURA_VERSION,
                true
            );
        }

        // Assets específicos para notificaciones (Fase 5, Item 5.4)
        if ($hook === 'aura-suite_page_aura-financial-notifications') {
            wp_enqueue_script(
                'aura-notifications',
                AURA_PLUGIN_URL . 'assets/js/notifications.js',
                array('jquery'),
                AURA_VERSION,
                true
            );
        }

        // Assets específicos para integraciones contables (Fase 5, Item 5.5)
        if (strpos($hook, '_page_aura-financial-integrations') !== false) {
            wp_enqueue_style(
                'aura-integrations',
                AURA_PLUGIN_URL . 'assets/css/integrations.css',
                array('aura-admin-styles'),
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-integrations',
                AURA_PLUGIN_URL . 'assets/js/integrations.js',
                array('jquery'),
                AURA_VERSION,
                true
            );
        }

        // Assets específicos para la página de edición de transacciones
        if ($hook === 'admin_page_aura-financial-edit-transaction'
            || strpos($hook, '_page_aura-financial-new-transaction') !== false) {
            // jQuery UI autocomplete para etiquetas (Fase 5, Item 5.2)
            wp_enqueue_script('jquery-ui-autocomplete');
            wp_add_inline_script('jquery-ui-autocomplete',
                'jQuery(function($){
                    $("input[data-autocomplete=\'aura-tags\']").each(function(){
                        var $input = $(this);
                        $input.autocomplete({
                            source: function(req, response){
                                $.post("' . admin_url('admin-ajax.php') . '",{
                                    action:"aura_tags_autocomplete",
                                    nonce:"' . wp_create_nonce('aura_tags_nonce') . '",
                                    term: req.term.split(/[,\s]+/).pop()
                                }).done(function(res){ response(res.success ? res.data : []); });
                            },
                            select: function(e, ui){
                                e.preventDefault();
                                var terms = $input.val().split(",").map(function(s){return s.trim();}).filter(Boolean);
                                terms.pop();
                                terms.push(ui.item.value);
                                $input.val(terms.join(", ") + ", ");
                            },
                            minLength: 1
                        });
                    });
                });'
            );
        }

        if ($hook === 'admin_page_aura-financial-edit-transaction') {
            // jQuery UI y DatePicker
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-datepicker-style', 
                'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css'
            );
            
            // CSS reutilizado del formulario de transacciones
            wp_enqueue_style(
                'aura-transactions-list',
                AURA_PLUGIN_URL . 'assets/css/transactions-list.css',
                array(),
                AURA_VERSION
            );
            
            // CSS del formulario de transacciones (incluye estilos de file upload)
            wp_enqueue_style(
                'aura-transaction-form',
                AURA_PLUGIN_URL . 'assets/css/transaction-form.css',
                array(),
                AURA_VERSION
            );
            
            // CSS específico para edición de transacciones
            wp_enqueue_style(
                'aura-transaction-edit',
                AURA_PLUGIN_URL . 'assets/css/transaction-edit.css',
                array('aura-transactions-list', 'aura-transaction-form'),
                AURA_VERSION
            );
            
            // JS de edición de transacciones
            wp_enqueue_script(
                'aura-transaction-edit',
                AURA_PLUGIN_URL . 'assets/js/transaction-edit.js',
                array('jquery', 'jquery-ui-datepicker'),
                AURA_VERSION,
                true
            );

            global $wpdb;
            $budgeted_map = array();
            $budget_rows = $wpdb->get_results(
                "SELECT DISTINCT category_id, area_id
                 FROM {$wpdb->prefix}aura_finance_budgets
                 WHERE is_active = 1
                   AND category_id IS NOT NULL
                   AND area_id IS NOT NULL"
            );
            foreach ($budget_rows as $br) {
                $cat_id = (int) $br->category_id;
                if (!isset($budgeted_map[$cat_id])) {
                    $budgeted_map[$cat_id] = array();
                }
                $budgeted_map[$cat_id][] = (int) $br->area_id;
            }
            
            // Localizar script con datos para AJAX
            wp_localize_script('aura-transaction-edit', 'auraTransactionEdit', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aura_transaction_edit_nonce'),
                'transactionNonce' => wp_create_nonce('aura_transaction_nonce'),
                'budgetsNonce' => wp_create_nonce('aura_budgets_nonce'),
                'budgetedAreasByCategory' => $budgeted_map,
                'labels' => array(
                    'date' => __('Fecha de Transacción', 'aura-suite'),
                    'transaction_date' => __('Fecha de Transacción', 'aura-suite'),
                    'type' => __('Tipo de Transacción', 'aura-suite'),
                    'category' => __('Categoría del Presupuesto', 'aura-suite'),
                    'category_id' => __('Categoría del Presupuesto', 'aura-suite'),
                    'expenseCategory' => __('Categoría del Gasto', 'aura-suite'),
                    'expense_category_id' => __('Categoría del Gasto', 'aura-suite'),
                    'amount' => __('Monto', 'aura-suite'),
                    'paymentMethod' => __('Método de Pago', 'aura-suite'),
                    'payment_method' => __('Método de Pago', 'aura-suite'),
                    'sourceAccount' => __('Cuenta Origen', 'aura-suite'),
                    'source_account_id' => __('Cuenta Origen', 'aura-suite'),
                    'destinationAccount' => __('Cuenta Destino', 'aura-suite'),
                    'destination_account_id' => __('Cuenta Destino', 'aura-suite'),
                    'area' => __('Área / Programa', 'aura-suite'),
                    'area_id' => __('Área / Programa', 'aura-suite'),
                    'description' => __('Descripción', 'aura-suite'),
                    'reference' => __('Número de Referencia', 'aura-suite'),
                    'reference_number' => __('Número de Referencia', 'aura-suite'),
                    'recipient' => __('Beneficiario / Proveedor / Tercero', 'aura-suite'),
                    'recipient_payer' => __('Beneficiario / Proveedor / Tercero', 'aura-suite'),
                    'third_party_id' => __('Beneficiario / Proveedor / Tercero', 'aura-suite'),
                    'related_user_id' => __('Usuario Vinculado', 'aura-suite'),
                    'related_user_concept' => __('Concepto de Vinculación', 'aura-suite'),
                    'notes' => __('Notas Internas', 'aura-suite'),
                    'tags' => __('Etiquetas', 'aura-suite'),
                    'receipt_file' => __('Comprobante', 'aura-suite'),
                    'status' => __('Estado', 'aura-suite'),
                    'empty' => __('(vacío)', 'aura-suite'),
                    'minChars' => __('caracteres mínimos', 'aura-suite'),
                    'saveChanges' => __('Guardar Cambios', 'aura-suite')
                ),
                'messages' => array(
                    'confirmSave' => __('¿Guardar los cambios realizados?', 'aura-suite'),
                    'confirmResetAll' => __('¿Restaurar todos los campos a sus valores originales?', 'aura-suite'),
                    'saving' => __('Guardando cambios...', 'aura-suite'),
                    'noChanges' => __('No se detectaron cambios en la transacción.', 'aura-suite'),
                    'allFieldsReset' => __('Todos los campos restaurados a sus valores originales.', 'aura-suite'),
                    'error' => __('Error al procesar la solicitud', 'aura-suite'),
                    'unsavedChanges' => __('Tienes cambios sin guardar. ¿Deseas salir de todas formas?', 'aura-suite'),
                    'significantAmountChange' => __('El cambio en el monto es significativo (%s%). Debes proporcionar un motivo.', 'aura-suite'),
                    'noBudgetsForArea' => __('Esta área no tiene presupuestos asignados para la fecha actual.', 'aura-suite'),
                    'noBudgetForCat' => __('No hay presupuesto activo para esta categoría en el área seleccionada.', 'aura-suite'),
                    'overspend' => __('⚠️ Este monto supera el disponible del presupuesto', 'aura-suite'),
                    'loadingCats' => __('Cargando categorías...', 'aura-suite'),
                    'areaFilteredByCategory' => __('Mostrando áreas con presupuesto para esta categoría.', 'aura-suite')
                ),
                'validation' => array(
                    'expenseCategoryRequired' => __('Debes seleccionar la categoría del gasto.', 'aura-suite'),
                    'amountRequired' => __('El monto debe ser mayor a 0.', 'aura-suite'),
                    'dateRequired' => __('La fecha es requerida.', 'aura-suite'),
                    'descriptionMinLength' => __('La descripción debe tener al menos 10 caracteres.', 'aura-suite'),
                    'sourceAccountRequired' => __('En un egreso debes seleccionar la cuenta origen o vincular un usuario para registrar reembolso por dinero personal.', 'aura-suite'),
                    'destinationAccountRequired' => __('En un ingreso debes seleccionar la cuenta destino.', 'aura-suite'),
                    'changeReasonRequired' => __('Debes proporcionar un motivo del cambio (mínimo 20 caracteres).', 'aura-suite')
                )
            ));
        }
        
        // Assets específicos para la página de papelera de transacciones
        if (strpos($hook, '_page_aura-financial-trash') !== false) {
            // Reutilizar CSS del listado de transacciones
            wp_enqueue_style(
                'aura-transactions-list',
                AURA_PLUGIN_URL . 'assets/css/transactions-list.css',
                array(),
                AURA_VERSION
            );
            
            // CSS específico para la papelera
            wp_enqueue_style(
                'aura-trash-transactions',
                AURA_PLUGIN_URL . 'assets/css/trash-transactions.css',
                array('aura-transactions-list'),
                AURA_VERSION
            );
            
            // Localizar nonce para AJAX
            wp_localize_script('jquery', 'auraTrashSettings', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aura_transaction_delete_nonce')
            ));
        }
        
        // Assets específicos para la página de aprobaciones pendientes
        if (strpos($hook, '_page_aura-financial-pending') !== false) {
            // Reutilizar CSS del listado de transacciones
            wp_enqueue_style(
                'aura-transactions-list',
                AURA_PLUGIN_URL . 'assets/css/transactions-list.css',
                array(),
                AURA_VERSION
            );
            
            // CSS reutilizado del modal de transacciones
            wp_enqueue_style(
                'aura-transaction-modal',
                AURA_PLUGIN_URL . 'assets/css/transaction-modal.css',
                array(),
                AURA_VERSION
            );
            
            // CSS específico para aprobaciones pendientes con versionado dinámico anti-caché
            $pending_css_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/css/pending-approvals.css');
            wp_enqueue_style(
                'aura-pending-approvals',
                AURA_PLUGIN_URL . 'assets/css/pending-approvals.css',
                array('aura-transactions-list', 'aura-transaction-modal'),
                $pending_css_ver
            );
            
            // JavaScript del modal de transacciones (para ver detalles)
            wp_enqueue_script(
                'aura-transaction-modal',
                AURA_PLUGIN_URL . 'assets/js/transaction-modal.js',
                array('jquery'),
                AURA_VERSION,
                true
            );
            
            // JavaScript de acciones de transacciones (aprobar/rechazar)
            $tx_list_js_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/js/transactions-list.js');

            wp_enqueue_script(
                'aura-transactions-list',
                AURA_PLUGIN_URL . 'assets/js/transactions-list.js',
                array('jquery'),
                $tx_list_js_ver,
                true
            );
            
            // Localizar script con datos para AJAX
            wp_localize_script('aura-transactions-list', 'auraTransactionsList', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('aura_transactions_list_nonce'),
                'messages' => array(
                    'confirmApprove' => __('¿Aprobar esta transacción?', 'aura-suite'),
                    'confirmReject' => __('¿Rechazar esta transacción?', 'aura-suite'),
                    'rejectReason' => __('Motivo del rechazo:', 'aura-suite'),
                    'confirmBulkApprove' => __('¿Aprobar las transacciones seleccionadas?', 'aura-suite'),
                    'confirmBulkReject' => __('¿Rechazar las transacciones seleccionadas?', 'aura-suite'),
                )
            ));
            
            // Localizar script del modal con datos para AJAX
            wp_localize_script('aura-transaction-modal', 'auraTransactionModal', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aura_transaction_modal_nonce'),
                'approvalNonce' => wp_create_nonce('aura_approval_nonce'),
                'currentUserId' => get_current_user_id(),
                'permissions' => array(
                    'canCreate' => current_user_can('aura_finance_create'),
                    'canEditOwn' => current_user_can('aura_finance_edit_own'),
                    'canEditAll' => current_user_can('aura_finance_edit_all'),
                    'canDeleteOwn' => current_user_can('aura_finance_delete_own'),
                    'canDeleteAll' => current_user_can('aura_finance_delete_all'),
                    'canApprove' => current_user_can('aura_finance_approve') || current_user_can('manage_options'),
                    'canViewOwn' => current_user_can('aura_finance_view_own') || current_user_can('manage_options'),
                    'canViewAll' => current_user_can('aura_finance_view_all') || current_user_can('manage_options')
                ),
                'messages' => array(
                    'loading' => __('Cargando...', 'aura-suite'),
                    'error' => __('Error al procesar la solicitud', 'aura-suite'),
                    'confirmApprove' => __('¿Confirmas la aprobación de esta transacción?', 'aura-suite'),
                    'confirmDelete' => __('¿Eliminar esta transacción? Esta acción no se puede deshacer.', 'aura-suite'),
                    'approveSuccess' => __('Transacción aprobada correctamente.', 'aura-suite'),
                    'rejectSuccess' => __('Transacción rechazada.', 'aura-suite')
                )
            ));
        }
        
        // CSS personalizado para ícono del menú (evita que se reduzca al seleccionar o navegar módulos)
        wp_add_inline_style('aura-admin-styles', '
            #adminmenu .toplevel_page_aura-suite .wp-menu-image {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                opacity: 1 !important;
            }
            #adminmenu .toplevel_page_aura-suite .wp-menu-image img {
                width: 20px !important;
                height: 20px !important;
                max-width: 20px !important;
                max-height: 20px !important;
                padding: 0 !important;
                margin: 0 auto !important;
                box-sizing: border-box !important;
                object-fit: contain !important;
                opacity: 0.85 !important;
                transition: opacity 0.15s ease !important;
            }
            #adminmenu .toplevel_page_aura-suite:hover .wp-menu-image img,
            #adminmenu .toplevel_page_aura-suite.current .wp-menu-image img,
            #adminmenu .toplevel_page_aura-suite.wp-has-current-submenu .wp-menu-image img,
            #adminmenu .toplevel_page_aura-suite.wp-menu-open .wp-menu-image img,
            #adminmenu .toplevel_page_aura-suite.selected .wp-menu-image img {
                width: 20px !important;
                height: 20px !important;
                max-width: 20px !important;
                max-height: 20px !important;
                padding: 0 !important;
                margin: 0 auto !important;
                box-sizing: border-box !important;
                object-fit: contain !important;
                opacity: 1 !important;
                filter: none !important;
            }
        ');
        
        // Solo cargar scripts pesados en páginas del plugin AURA
        $is_aura_page = (
            strpos($hook, 'aura-') !== false ||
            strpos($hook, 'aura_') !== false ||
            strpos($hook, 'toplevel_page_aura') !== false ||
            strpos($hook, 'finanzas_page') !== false ||
            strpos($hook, 'inventario_page') !== false ||
            strpos($hook, 'biblioteca_page') !== false ||
            strpos($hook, 'estudiantes_page') !== false ||
            (isset($_GET['page']) && strpos($_GET['page'], 'aura') === 0)
        );

        if ( $is_aura_page ) {
            // 1. Fuentes globales de Aura
            wp_enqueue_style(
                'aura-inter-font',
                'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap',
                array(),
                null
            );

            // 2. Sistema de Diseño Base CSS (Centralizado)
            wp_enqueue_style(
                'aura-design-system',
                AURA_PLUGIN_URL . 'assets/css/aura-design-system.css',
                array(),
                AURA_VERSION . '.' . (file_exists(AURA_PLUGIN_DIR . 'assets/css/aura-design-system.css') ? filemtime(AURA_PLUGIN_DIR . 'assets/css/aura-design-system.css') : time())
            );

            // 3. Controlador UI Global JS
            wp_enqueue_script(
                'aura-ui-core',
                AURA_PLUGIN_URL . 'assets/js/aura-ui.js',
                array('jquery'),
                AURA_VERSION . '.' . (file_exists(AURA_PLUGIN_DIR . 'assets/js/aura-ui.js') ? filemtime(AURA_PLUGIN_DIR . 'assets/js/aura-ui.js') : time()),
                true
            );

            // 4. Inyección Global de Footer Corporativo en todas las pantallas de Aura
            add_filter('admin_footer_text', function($default_text) {
                return sprintf(
                    __('Desarrollado con ❤️ por %s &nbsp;|&nbsp; © %s AURA Business Suite', 'aura-suite'),
                    '<strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.216.694.825.576C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>DiGiraldo</a></strong>',
                    date('Y')
                );
            }, 99);

            add_filter('update_footer', function($default_version) {
                return '<span class="aura-footer-version">AURA Suite v' . AURA_VERSION . '</span>';
            }, 99);

            // Chart.js para gráficos
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                array(),
                '4.4.0',
                true
            );

            // Scripts personalizados del admin
            wp_enqueue_script(
                'aura-admin-scripts',
                AURA_PLUGIN_URL . 'assets/js/admin-scripts.js',
                array('jquery', 'chartjs'),
                AURA_VERSION,
                true
            );

            // Scripts de gráficos
            wp_enqueue_script(
                'aura-charts',
                AURA_PLUGIN_URL . 'assets/js/charts.js',
                array('jquery', 'chartjs'),
                AURA_VERSION,
                true
            );

            // DataTables §5.6 — CSS (core + Responsive)
            wp_enqueue_style(
                'datatables-css',
                'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css',
                array(),
                '2.2.2'
            );
            wp_enqueue_style(
                'datatables-responsive-css',
                'https://cdn.datatables.net/responsive/3.0.4/css/responsive.dataTables.min.css',
                array( 'datatables-css' ),
                '3.0.4'
            );

            // DataTables §5.6 — JS (core + Responsive)
            wp_enqueue_script(
                'datatables-js',
                'https://cdn.datatables.net/2.2.2/js/dataTables.min.js',
                array( 'jquery' ),
                '2.2.2',
                true
            );
            wp_enqueue_script(
                'datatables-responsive-js',
                'https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js',
                array( 'datatables-js' ),
                '3.0.4',
                true
            );
        }

        // Localizar script con datos para AJAX (solo si el script fue registrado)
        if ( $is_aura_page ) {
            wp_localize_script('aura-admin-scripts', 'auraData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('aura_nonce'),
                'strings' => array(
                    'confirmDelete' => __('¿Estás seguro de eliminar este elemento?', 'aura-suite'),
                    'error'         => __('Ha ocurrido un error. Por favor, intenta nuevamente.', 'aura-suite'),
                ),
            ));
        }

        // ── Configuración Global (aura-settings) ────────────────────────────────
        if ( $hook === 'aura-suite_page_aura-settings' ) {
            wp_enqueue_media();

            // Estilos dedicados estandarizados según prompt-maestro.md
            wp_enqueue_style(
                'aura-settings-page',
                AURA_PLUGIN_URL . 'assets/css/settings-page.css',
                array( 'aura-admin-styles', 'aura-design-system' ),
                AURA_VERSION . '.' . (file_exists(AURA_PLUGIN_DIR . 'assets/css/settings-page.css') ? filemtime(AURA_PLUGIN_DIR . 'assets/css/settings-page.css') : time())
            );

            // ApexCharts (gráficos interactivos §5.7)
            wp_enqueue_script(
                'apexcharts',
                'https://cdn.jsdelivr.net/npm/apexcharts@3.46.0/dist/apexcharts.min.js',
                array(),
                '3.46.0',
                true
            );
        }


        // ── Assets Inventario — Dashboard ────────────────────────────────────────
        if ( $hook === 'toplevel_page_aura-inventory' ) {
            wp_enqueue_script(
                'apexcharts',
                'https://cdn.jsdelivr.net/npm/apexcharts@3.46.0/dist/apexcharts.min.js',
                [],
                '3.46.0',
                true
            );
            // Reutilizar estilos base de equipos (KPI cards, badges, etc.)
            wp_enqueue_style(
                'aura-inventory-equipment',
                AURA_PLUGIN_URL . 'assets/css/inventory-equipment.css',
                [ 'aura-admin-styles' ],
                AURA_VERSION
            );
            wp_enqueue_style(
                'aura-inventory-dashboard',
                AURA_PLUGIN_URL . 'assets/css/inventory-dashboard.css',
                [ 'aura-admin-styles', 'aura-inventory-equipment' ],
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-inventory-dashboard',
                AURA_PLUGIN_URL . 'assets/js/inventory-dashboard.js',
                [ 'jquery', 'apexcharts' ],
                AURA_VERSION,
                true
            );
        }

        // ── Assets Inventario — Equipos ───────────────────────────────────────────
        if ( in_array( $hook, [
            'inventario_page_aura-inventory-equipment',
            'inventario_page_aura-inventory-new-equipment',
        ] ) ) {
            wp_enqueue_media();
            wp_enqueue_script( 'jquery-ui-datepicker' );
            wp_enqueue_style( 'jquery-ui-datepicker-style',
                'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css',
                [], '1.13.2' );
            // Cropper.js — recorte de imágenes de equipos
            wp_enqueue_style( 'cropperjs',
                'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css',
                [], '1.6.2' );
            wp_enqueue_script( 'cropperjs',
                'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js',
                [], '1.6.2', true );
            wp_enqueue_style(
                'aura-inventory-equipment',
                AURA_PLUGIN_URL . 'assets/css/inventory-equipment.css',
                [ 'aura-admin-styles' ],
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-inventory-equipment',
                AURA_PLUGIN_URL . 'assets/js/inventory-equipment.js',
                [ 'jquery', 'jquery-ui-datepicker', 'cropperjs' ],
                AURA_VERSION,
                true
            );
        }

        // ── Assets Inventario — Mantenimientos ───────────────────────────────────
        if ( in_array( $hook, [
            'inventario_page_aura-inventory-maintenance',
            'inventario_page_aura-inventory-new-maintenance',
            'admin_page_aura-inventory-new-maintenance',
        ] ) ) {
            wp_enqueue_media();
            wp_enqueue_script( 'jquery-ui-datepicker' );
            wp_enqueue_style( 'jquery-ui-datepicker-style',
                'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css',
                [], '1.13.2' );
            wp_enqueue_style(
                'aura-inventory-equipment',
                AURA_PLUGIN_URL . 'assets/css/inventory-equipment.css',
                [ 'aura-admin-styles' ],
                AURA_VERSION
            );
            wp_enqueue_style(
                'aura-inventory-maintenance',
                AURA_PLUGIN_URL . 'assets/css/inventory-maintenance.css',
                [ 'aura-admin-styles', 'aura-inventory-equipment' ],
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-inventory-equipment',
                AURA_PLUGIN_URL . 'assets/js/inventory-equipment.js',
                [ 'jquery', 'jquery-ui-datepicker' ],
                AURA_VERSION,
                true
            );
            wp_enqueue_script(
                'aura-inventory-maintenance',
                AURA_PLUGIN_URL . 'assets/js/inventory-maintenance.js',
                [ 'jquery', 'jquery-ui-datepicker', 'aura-inventory-equipment' ],
                AURA_VERSION,
                true
            );
        }

        // ── Assets Inventario — Préstamos (FASE 5) ────────────────────
        if ( $hook === 'inventario_page_aura-inventory-loans' ) {
            wp_enqueue_style(
                'aura-inventory-equipment',
                AURA_PLUGIN_URL . 'assets/css/inventory-equipment.css',
                [ 'aura-admin-styles' ],
                AURA_VERSION
            );
            wp_enqueue_style(
                'aura-inventory-loans',
                AURA_PLUGIN_URL . 'assets/css/inventory-loans.css',
                [ 'aura-admin-styles', 'aura-inventory-equipment' ],
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-inventory-loans',
                AURA_PLUGIN_URL . 'assets/js/inventory-loans.js',
                [ 'jquery' ],
                AURA_VERSION,
                true
            );
        }

        // ── Assets Inventario — Configuración ─────────────────────────
        if ( $hook === 'inventario_page_aura-inventory-settings' ) {
            wp_enqueue_style(
                'aura-inventory-settings',
                AURA_PLUGIN_URL . 'assets/css/inventory-settings.css',
                [ 'aura-admin-styles' ],
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-inventory-settings',
                AURA_PLUGIN_URL . 'assets/js/inventory-settings.js',
                [ 'jquery' ],
                AURA_VERSION,
                true
            );
        }

        // ── Assets Gestión de Permisos (CBAC) ──────────────────────────
        if ( strpos( $hook, '_page_aura-permissions' ) !== false ) {
            $perms_css_ver = AURA_VERSION . '.' . ( file_exists( AURA_PLUGIN_DIR . 'assets/css/permissions-page.css' ) ? filemtime( AURA_PLUGIN_DIR . 'assets/css/permissions-page.css' ) : time() );
            $perms_js_ver  = AURA_VERSION . '.' . ( file_exists( AURA_PLUGIN_DIR . 'assets/js/permissions-page.js' ) ? filemtime( AURA_PLUGIN_DIR . 'assets/js/permissions-page.js' ) : time() );

            wp_enqueue_style(
                'aura-permissions-page',
                AURA_PLUGIN_URL . 'assets/css/permissions-page.css',
                array( 'aura-admin-styles', 'aura-design-system' ),
                $perms_css_ver
            );

            wp_enqueue_script(
                'aura-permissions-page',
                AURA_PLUGIN_URL . 'assets/js/permissions-page.js',
                array( 'jquery', 'aura-ui-core' ),
                $perms_js_ver,
                true
            );

            $current_sel_user_id = !empty($_GET['user_id']) ? absint($_GET['user_id']) : 0;
            $current_sel_user_name = '';
            if ($current_sel_user_id) {
                $sel_u = get_user_by('id', $current_sel_user_id);
                if ($sel_u) {
                    $current_sel_user_name = $sel_u->display_name;
                }
            }

            wp_localize_script( 'aura-permissions-page', 'auraPermissionsData', array(
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'aura_create_user_nonce' ),
                'permissionsNonce' => wp_create_nonce( 'aura_permissions_nonce' ),
                'selectedUserId'   => $current_sel_user_id,
                'selectedUserName' => $current_sel_user_name,
                'adminUrl'         => admin_url( 'admin.php' ),
                'i18n'      => array(
                    'selectUserAlert'  => __( 'Por favor seleccione un usuario de la lista.', 'aura-suite' ),
                    'requiredFields'   => __( 'Nombre, correo electrónico y contraseña son obligatorios.', 'aura-suite' ),
                    'creating'         => __( 'Creando...', 'aura-suite' ),
                    'createAndAssign'  => __( 'Crear y asignar permisos', 'aura-suite' ),
                    'connError'        => __( 'Error de conexión. Inténtalo de nuevo.', 'aura-suite' ),
                    'confirmTemplate'  => __( '¿Aplicar la plantilla "%s"?\n\nSe configurarán %d permisos recomendados para este usuario.', 'aura-suite' ),
                    'noActiveMatches'  => __( 'No se encontraron usuarios activos que coincidan con la búsqueda.', 'aura-suite' ),
                    'noInactiveMatches'=> __( 'No se encontraron usuarios de WordPress que coincidan con la búsqueda.', 'aura-suite' ),
                    'showingTemplates' => __( 'Mostrando %d perfiles estándar', 'aura-suite' ),
                    'showingTemplatesOf' => __( 'Mostrando %d de %d perfiles', 'aura-suite' ),
                    'permActive'       => __( 'permiso activo', 'aura-suite' ),
                    'permsActive'      => __( 'permisos activos', 'aura-suite' ),
                    'results'          => __( 'resultados', 'aura-suite' ),
                    'noResults'        => __( 'Sin resultados', 'aura-suite' ),
                ),
            ) );
        }
    }
    
    /**
     * Encolar assets del frontend
     */
    public function enqueue_frontend_assets() {
        // CSS del frontend
        wp_enqueue_style(
            'aura-frontend-styles',
            AURA_PLUGIN_URL . 'assets/css/frontend-styles.css',
            array(),
            AURA_VERSION
        );
        
        // Chart.js para dashboards frontend
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );
    }
    
    /**
     * Personalizar logo del login - FUNCIONES DESACTIVADAS
     * Estas funciones están comentadas para mantener el login de WordPress original
     */
    /*
    public function custom_login_logo() {
        ?>
        <style type="text/css">
            #login h1 a, .login h1 a {
                background-image: url(<?php echo esc_url(AURA_PLUGIN_URL . 'aura-icono.svg'); ?>);
                height: 120px;
                width: 120px;
                background-size: contain;
                background-repeat: no-repeat;
                padding-bottom: 10px;
            }
            .login {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .login form {
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            }
        </style>
        <?php
    }
    
    public function custom_login_logo_url() {
        return home_url();
    }
    
    public function custom_login_logo_url_title() {
        return __('Aura - Aplicaciones Unificadas para Recursos Administrativos', 'aura-suite');
    }
    */
    
    /**
     * Mostrar logo de la organización en la página de login (si está activado).
     */
    public function org_login_logo() {
        if ( ! get_option('aura_org_logo_in_login', false) ) {
            return;
        }
        $logo_url = aura_get_org_logo_url('medium');
        if ( ! $logo_url ) {
            return;
        }
        ?>
        <style type="text/css">
            #login h1 a, .login h1 a {
                background-image: url(<?php echo esc_url($logo_url); ?>);
                background-size: contain;
                background-repeat: no-repeat;
                background-position: center;
                height: 90px;
                width: 200px;
            }
        </style>
        <?php
    }

    /**
     * Inyectar estilos del icono del menú en admin_head para evitar que se encoja al seleccionarse
     */
    public function output_admin_menu_icon_styles() {
        ?>
        <style id="aura-admin-menu-icon-styles">
            #adminmenu .toplevel_page_aura-suite .wp-menu-image {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                opacity: 1 !important;
            }
            #adminmenu .toplevel_page_aura-suite .wp-menu-image img {
                width: 20px !important;
                height: 20px !important;
                max-width: 20px !important;
                max-height: 20px !important;
                padding: 0 !important;
                margin: 0 auto !important;
                box-sizing: border-box !important;
                object-fit: contain !important;
                opacity: 0.85 !important;
                transition: opacity 0.15s ease !important;
            }
            #adminmenu .toplevel_page_aura-suite:hover .wp-menu-image img,
            #adminmenu .toplevel_page_aura-suite.current .wp-menu-image img,
            #adminmenu .toplevel_page_aura-suite.wp-has-current-submenu .wp-menu-image img,
            #adminmenu .toplevel_page_aura-suite.wp-menu-open .wp-menu-image img,
            #adminmenu .toplevel_page_aura-suite.selected .wp-menu-image img {
                width: 20px !important;
                height: 20px !important;
                max-width: 20px !important;
                max-height: 20px !important;
                padding: 0 !important;
                margin: 0 auto !important;
                box-sizing: border-box !important;
                object-fit: contain !important;
                opacity: 1 !important;
                filter: none !important;
            }
        </style>
        <?php
    }

    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        // ═══════════════════════════════════════════════════════════
        // MENÚ RAÍZ — AURA SUITE
        // ═══════════════════════════════════════════════════════════
        add_menu_page(
            __('Aura Suite', 'aura-suite'),
            __('Aura Suite', 'aura-suite'),
            'read',
            'aura-suite',
            array($this, 'render_main_dashboard'),
            AURA_PLUGIN_URL . 'aura-icono.svg',
            3
        );

        // Dashboard principal (duplica el entry point del menú raíz)
        add_submenu_page(
            'aura-suite',
            __('Dashboard', 'aura-suite'),
            __('🏠 Dashboard', 'aura-suite'),
            'read',
            'aura-suite',
            array($this, 'render_main_dashboard')
        );

        // Notificaciones globales
        add_submenu_page(
            'aura-suite',
            __('Notificaciones', 'aura-suite'),
            __('🔔 Notificaciones', 'aura-suite'),
            'read',
            'aura-financial-notifications',
            array($this, 'render_notifications_page')
        );

        // Configuración (solo administradores)
        if (current_user_can('aura_admin_settings')) {
            add_submenu_page(
                'aura-suite',
                __('Configuración', 'aura-suite'),
                __('⚙️ Configuración', 'aura-suite'),
                'aura_admin_settings',
                'aura-settings',
                array($this, 'render_settings_page')
            );
        }

        // Gestión de Permisos (asignar permisos O crear usuarios)
        if (current_user_can('aura_admin_permissions_assign') || current_user_can('aura_admin_users_create')) {
            // La capability mínima para ver el menú es la que el usuario tenga
            $perms_cap = current_user_can('aura_admin_permissions_assign') ? 'aura_admin_permissions_assign' : 'aura_admin_users_create';
            add_submenu_page(
                'aura-suite',
                __('Gestión de Permisos', 'aura-suite'),
                __('🔐 Permisos', 'aura-suite'),
                $perms_cap,
                'aura-permissions',
                array($this, 'render_permissions_page')
            );
        }

        // Directorio Maestro de Terceros (Accesible según capabilities granulares)
        $has_tp_access = (
            current_user_can('manage_options') ||
            current_user_can('aura_third_parties_view') ||
            current_user_can('aura_third_parties_create') ||
            current_user_can('aura_third_parties_edit') ||
            current_user_can('aura_third_parties_delete') ||
            current_user_can('aura_finance_create') ||
            current_user_can('aura_finance_view_all')
        );

        if ($has_tp_access) {
            add_submenu_page(
                'aura-suite',
                __('Directorio de Terceros', 'aura-suite'),
                __('👥 Terceros', 'aura-suite'),
                'read',
                'aura-third-parties',
                array('Aura_Third_Parties', 'render_admin_page')
            );
        }

        // ═══════════════════════════════════════════════════════════
        // MENÚ RAÍZ — FINANZAS (módulo independiente)
        // Visible para cualquier usuario con al menos un permiso finance
        // ═══════════════════════════════════════════════════════════
        $has_finance_access = (
            current_user_can('manage_options') ||
            current_user_can('aura_finance_view_own') ||
            current_user_can('aura_finance_view_all') ||
            current_user_can('aura_finance_create') ||
            current_user_can('aura_finance_approve') ||
            current_user_can('aura_finance_charts') ||
            current_user_can('aura_finance_view_user_summary') ||
            current_user_can('aura_finance_user_ledger') ||
            current_user_can('aura_finance_integrations') ||
            current_user_can('aura_finance_audit') ||
            current_user_can('aura_finance_budgets') ||
            current_user_can('aura_finance_tags') ||
            current_user_can('aura_finance_import') ||
            current_user_can('aura_finance_accounts_manage') ||
            current_user_can('aura_areas_view_own')
        );

        if ($has_finance_access) {
            add_menu_page(
                __('Finanzas', 'aura-suite'),
                __('Finanzas', 'aura-suite'),
                'read',
                'aura-financial-dashboard',
                array('Aura_Financial_Dashboard', 'render'),
                'dashicons-chart-bar',
                3.1
            );

            // ── GRUPO 1: Visión general ──────────────────────────
            // Dashboard Financiero (entry point del menú raíz)
            add_submenu_page(
                'aura-financial-dashboard',
                __('Dashboard Financiero', 'aura-suite'),
                __('Dashboard Financiero', 'aura-suite'),
                'read',
                'aura-financial-dashboard',
                array('Aura_Financial_Dashboard', 'render')
            );

            // Mi Dashboard Financiero Personal
            if (
                current_user_can('aura_finance_view_user_summary') ||
                current_user_can('aura_finance_view_all') ||
                current_user_can('manage_options')
            ) {
                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Mi Dashboard Financiero', 'aura-suite'),
                    __('Mi Finanzas', 'aura-suite'),
                    'read',
                    'aura-my-finance',
                    array('Aura_Financial_User_Dashboard', 'render')
                );
            }

            // Resumen de Usuarios
            if (current_user_can('aura_finance_view_user_summary') || current_user_can('aura_finance_view_all') || current_user_can('manage_options')) {
                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Resumen de Usuarios', 'aura-suite'),
                    __('Resumen Usuarios', 'aura-suite'),
                    'read',
                    'aura-user-summary',
                    array('Aura_Financial_User_Summary', 'render')
                );
            }

            // ── GRUPO 2: Operaciones / Transacciones ─────────────
            if (current_user_can('aura_finance_view_own') || current_user_can('aura_finance_view_all') || current_user_can('manage_options')) {
                $trans_label = (current_user_can('aura_finance_view_all') || current_user_can('manage_options'))
                    ? __('Transacciones', 'aura-suite')
                    : __('Mis Registros', 'aura-suite');

                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Transacciones', 'aura-suite'),
                    $trans_label,
                    'read',
                    'aura-financial-transactions',
                    array($this, 'render_transactions_list')
                );
            }

            if (current_user_can('aura_finance_create') || current_user_can('manage_options')) {
                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Nueva Transacción', 'aura-suite'),
                    __('+ Nueva Transacción', 'aura-suite'),
                    'read',
                    'aura-financial-new-transaction',
                    array($this, 'render_transaction_form')
                );
            }

            // Aprobaciones Pendientes
            if (current_user_can('aura_finance_approve') || current_user_can('manage_options')) {
                $pending_count = class_exists('Aura_Financial_Approval') ? Aura_Financial_Approval::get_pending_count() : 0;
                $pending_label = __('Pendientes', 'aura-suite');
                if ($pending_count > 0) {
                    $pending_label .= ' <span class="awaiting-mod">' . $pending_count . '</span>';
                }

                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Aprobaciones Pendientes', 'aura-suite'),
                    $pending_label,
                    'read',
                    'aura-financial-pending',
                    array($this, 'render_pending_list')
                );
            }

            // Papelera
            if (current_user_can('aura_finance_delete_own') || current_user_can('aura_finance_delete_all') || current_user_can('manage_options')) {
                $trash_count = class_exists('Aura_Financial_Transactions_Delete') ? Aura_Financial_Transactions_Delete::get_trash_count() : 0;
                $trash_label = __('Papelera', 'aura-suite');
                if ($trash_count > 0) {
                    $trash_label .= ' <span class="awaiting-mod">' . $trash_count . '</span>';
                }

                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Papelera de Transacciones', 'aura-suite'),
                    $trash_label,
                    'read',
                    'aura-financial-trash',
                    array($this, 'render_trash_list')
                );
            }

            // ── GRUPO 3: Categorías y Presupuestos ───────────────
            // (Categorías Financieras se registra en class-financial-categories.php)
            if (current_user_can('aura_finance_view_all') || current_user_can('manage_options') || current_user_can('aura_finance_budgets') || current_user_can('aura_areas_view_own')) {
                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Presupuestos', 'aura-suite'),
                    __('Presupuestos', 'aura-suite'),
                    'read',
                    'aura-financial-budgets',
                    array($this, 'render_budgets_page')
                );
            }

            // ── GRUPO 4: Análisis e Informes ─────────────────────
            if (current_user_can('aura_finance_view_own') || current_user_can('aura_finance_view_all') || current_user_can('aura_finance_charts') || current_user_can('manage_options')) {
                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Reportes Financieros', 'aura-suite'),
                    __('Reportes', 'aura-suite'),
                    'read',
                    'aura-financial-reports',
                    array('Aura_Financial_Reports', 'render')
                );

                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Análisis Visual', 'aura-suite'),
                    __('Análisis Visual', 'aura-suite'),
                    'read',
                    'aura-financial-analytics',
                    array('Aura_Financial_Analytics', 'render')
                );
            }

            // Libro Mayor por Usuario
            if (
                current_user_can('aura_finance_user_ledger') ||
                current_user_can('aura_finance_view_all') ||
                current_user_can('manage_options')
            ) {
                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Libro Mayor por Usuario', 'aura-suite'),
                    __('Libro Mayor', 'aura-suite'),
                    'read',
                    'aura-user-ledger',
                    array('Aura_Financial_User_Ledger', 'render')
                );
            }

            // ── GRUPO 5: Herramientas ────────────────────────────
            if (current_user_can('aura_finance_create') || current_user_can('aura_finance_import') || current_user_can('manage_options')) {
                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Importar Transacciones', 'aura-suite'),
                    __('Importar CSV/Excel', 'aura-suite'),
                    'read',
                    'aura-financial-import',
                    array($this, 'render_import_page')
                );
            }

            add_submenu_page(
                'aura-financial-dashboard',
                __('Etiquetas', 'aura-suite'),
                __('Etiquetas', 'aura-suite'),
                'read',
                'aura-financial-tags',
                array($this, 'render_tags_page')
            );

            add_submenu_page(
                'aura-financial-dashboard',
                __('Búsqueda Avanzada', 'aura-suite'),
                __('Búsqueda Avanzada', 'aura-suite'),
                'read',
                'aura-financial-search',
                array($this, 'render_search_page')
            );

            // ── GRUPO 6: Administración (solo admins/auditores) ───
            if (current_user_can('manage_options') || current_user_can('aura_auditor')) {
                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Auditoría', 'aura-suite'),
                    __('Auditoría', 'aura-suite'),
                    'manage_options',
                    'aura-financial-audit',
                    array($this, 'render_audit_page')
                );
            }

            if (current_user_can('manage_options')) {
                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Integraciones Contables', 'aura-suite'),
                    __('Integraciones Cont.', 'aura-suite'),
                    'manage_options',
                    'aura-financial-integrations',
                    array($this, 'render_integrations_page')
                );
            }


            // Páginas ocultas (sin entrada de menú visible)
            if (current_user_can('aura_finance_edit_own') || current_user_can('aura_finance_edit_all') || current_user_can('manage_options')) {
                add_submenu_page(
                    null,
                    __('Editar Transacción', 'aura-suite'),
                    __('Editar Transacción', 'aura-suite'),
                    'read',
                    'aura-financial-edit-transaction',
                    array($this, 'render_transaction_edit_form')
                );
            }
        } // fin $has_finance_access

        // ═══════════════════════════════════════════════════════
        // MENÚ RAÍZ — INVENTARIO (módulo independiente)
        // Visible para cualquier usuario con al menos un permiso
        // ═══════════════════════════════════════════════════════
        $has_inventory_access = (
            current_user_can( 'manage_options' ) ||
            current_user_can( 'aura_inventory_view_all' ) ||
            current_user_can( 'aura_inventory_create' ) ||
            current_user_can( 'aura_inventory_maintenance_view' ) ||
            current_user_can( 'aura_inventory_maintenance_register' ) ||
            current_user_can( 'aura_inventory_reports' )
        );

        if ( $has_inventory_access ) {
            add_menu_page(
                __( 'Inventario — AURA', 'aura-suite' ),
                __( 'Inventario', 'aura-suite' ),
                'read',
                'aura-inventory',
                array( 'Aura_Inventory_Dashboard', 'render' ),
                'dashicons-clipboard',
                3.2
            );

            // Dashboard (entrada raíz del menú)
            add_submenu_page(
                'aura-inventory',
                __( 'Dashboard Inventario', 'aura-suite' ),
                __( 'Dashboard', 'aura-suite' ),
                'read',
                'aura-inventory',
                array( 'Aura_Inventory_Dashboard', 'render' )
            );

            // Equipos y Herramientas
            if ( current_user_can( 'aura_inventory_view_all' ) || current_user_can( 'manage_options' ) ) {
                add_submenu_page(
                    'aura-inventory',
                    __( 'Equipos y Herramientas', 'aura-suite' ),
                    __( 'Equipos', 'aura-suite' ),
                    'read',
                    'aura-inventory-equipment',
                    array( 'Aura_Inventory_Equipment', 'render_list' )
                );
            }

            // Registrar Equipo Nuevo
            if ( current_user_can( 'aura_inventory_create' ) || current_user_can( 'manage_options' ) ) {
                add_submenu_page(
                    'aura-inventory',
                    __( 'Registrar Equipo', 'aura-suite' ),
                    __( '+ Nuevo Equipo', 'aura-suite' ),
                    'read',
                    'aura-inventory-new-equipment',
                    array( 'Aura_Inventory_Equipment', 'render_form' )
                );
            }

            // Mantenimientos
            if ( current_user_can( 'aura_inventory_maintenance_view' ) || current_user_can( 'manage_options' ) ) {
                add_submenu_page(
                    'aura-inventory',
                    __( 'Mantenimientos', 'aura-suite' ),
                    __( 'Mantenimientos', 'aura-suite' ),
                    'read',
                    'aura-inventory-maintenance',
                    array( 'Aura_Inventory_Maintenance', 'render_list' )
                );
            }

            // Préstamos
            if ( current_user_can( 'aura_inventory_checkout' ) || current_user_can( 'aura_inventory_checkin' ) || current_user_can( 'manage_options' ) ) {
                add_submenu_page(
                    'aura-inventory',
                    __( 'Préstamos de Equipos', 'aura-suite' ),
                    __( 'Préstamos', 'aura-suite' ),
                    'read',
                    'aura-inventory-loans',
                    array( 'Aura_Inventory_Loans', 'render_list' )
                );
            }

            // Reportes
            if ( current_user_can( 'aura_inventory_reports' ) || current_user_can( 'manage_options' ) ) {
                add_submenu_page(
                    'aura-inventory',
                    __( 'Reportes de Inventario', 'aura-suite' ),
                    __( 'Reportes', 'aura-suite' ),
                    'read',
                    'aura-inventory-reports',
                    array( 'Aura_Inventory_Reports', 'render' )
                );
            }

            // Configuración
            if ( current_user_can( 'aura_inventory_categories' ) || current_user_can( 'manage_options' ) ) {
                add_submenu_page(
                    'aura-inventory',
                    __( 'Configuración de Inventario', 'aura-suite' ),
                    __( 'Configuración', 'aura-suite' ),
                    'read',
                    'aura-inventory-settings',
                    array( 'Aura_Inventory_Categories', 'render_settings' )
                );
            }

            // Página oculta: Registrar / Editar Mantenimiento
            if ( current_user_can( 'aura_inventory_maintenance_create' ) || current_user_can( 'aura_inventory_maintenance_edit' ) || current_user_can( 'manage_options' ) ) {
                add_submenu_page(
                    null,
                    __( 'Registrar Mantenimiento', 'aura-suite' ),
                    __( 'Registrar Mantenimiento', 'aura-suite' ),
                    'read',
                    'aura-inventory-new-maintenance',
                    array( 'Aura_Inventory_Maintenance', 'render_form' )
                );
            }
        } // fin $has_inventory_access
    }
    
    /**
     * Renderizar dashboard principal
     */
    public function render_main_dashboard() {
        include AURA_PLUGIN_DIR . 'templates/main-dashboard.php';
    }
    
    /**
     * Renderizar listado de transacciones con filtros avanzados
     */
    public function render_transactions_list() {
        include AURA_PLUGIN_DIR . 'templates/financial/transactions-list.php';
    }

    /**
     * Renderizar página de importación CSV/Excel (Fase 4, Item 4.2)
     */
    public function render_import_page() {
        Aura_Financial_Import::render();
    }

    /**
     * Renderizar página de presupuestos (Fase 5, Item 5.1)
     */
    public function render_budgets_page() {
        Aura_Financial_Budgets::render();
    }

    /**
     * Renderizar página de etiquetas (Fase 5, Item 5.2)
     */
    public function render_tags_page() {
        Aura_Financial_Tags::render();
    }

    /**
     * Renderizar página de búsqueda avanzada (Fase 5, Item 5.2)
     */
    public function render_search_page() {
        Aura_Financial_Search::render();
    }

    /**
     * Renderizar página de auditoría y trazabilidad (Fase 5, Item 5.3)
     */
    public function render_audit_page() {
        Aura_Financial_Audit::render();
    }

    /**
     * Renderizar página de notificaciones (Fase 5, Item 5.4)
     */
    public function render_notifications_page() {
        Aura_Financial_Notifications::render();
    }

    /**
     * Renderizar página de integraciones contables (Fase 5, Item 5.5)
     */
    public function render_integrations_page() {
        Aura_Financial_Integrations::render();
    }

    /**
     * Renderizar formulario de nueva transacción
     */
    public function render_transaction_form() {
        include AURA_PLUGIN_DIR . 'templates/financial/transaction-form.php';
    }
    
    /**
     * Renderizar página de papelera de transacciones
     */
    public function render_trash_list() {
        include AURA_PLUGIN_DIR . 'templates/financial/trash-transactions.php';
    }
    
    /**
     * Renderizar página de aprobaciones pendientes
     */
    public function render_pending_list() {
        include AURA_PLUGIN_DIR . 'templates/financial/pending-transactions.php';
    }
    
    /**
     * Renderizar formulario de edición de transacción
     */
    public function render_transaction_edit_form() {
        include AURA_PLUGIN_DIR . 'templates/financial/edit-transaction.php';
    }
    
    /**
     * Renderizar página de configuración
     */
    public function render_settings_page() {
        include AURA_PLUGIN_DIR . 'templates/settings-page.php';
    }
    
    /**
     * Renderizar página de permisos
     */
    public function render_permissions_page() {
        include AURA_PLUGIN_DIR . 'templates/permissions-page.php';
    }

    /**
     * AJAX: Crear un nuevo usuario de WordPress con rol Suscriptor.
     * Requiere capability aura_admin_users_create.
     */
    public function ajax_create_user() {
        check_ajax_referer( 'aura_create_user_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_admin_users_create' ) ) {
            wp_send_json_error( array( 'message' => __( 'No tienes permiso para crear usuarios.', 'aura-suite' ) ), 403 );
        }

        $first_name   = sanitize_text_field( $_POST['first_name'] ?? '' );
        $last_name    = sanitize_text_field( $_POST['last_name']  ?? '' );
        $email        = sanitize_email( $_POST['email'] ?? '' );
        $phone        = sanitize_text_field( $_POST['phone'] ?? '' );
        $password     = $_POST['password'] ?? '';
        $custom_login = sanitize_user( $_POST['user_login'] ?? '', true );

        if ( empty( $first_name ) || empty( $email ) || empty( $password ) ) {
            wp_send_json_error( array( 'message' => __( 'Nombre, email y contraseña son obligatorios.', 'aura-suite' ) ), 422 );
        }

        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'El email no es válido.', 'aura-suite' ) ), 422 );
        }

        if ( email_exists( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Ya existe un usuario con ese email.', 'aura-suite' ) ), 409 );
        }

        if ( strlen( $password ) < 8 ) {
            wp_send_json_error( array( 'message' => __( 'La contraseña debe tener al menos 8 caracteres.', 'aura-suite' ) ), 422 );
        }

        // Determinar username (personalizado o autogenerado a partir del email)
        if ( ! empty( $custom_login ) ) {
            if ( username_exists( $custom_login ) ) {
                wp_send_json_error( array( 'message' => sprintf( __( 'El nombre de usuario "%s" ya existe. Por favor ingresa otro.', 'aura-suite' ), esc_html( $custom_login ) ) ), 409 );
            }
            $username = $custom_login;
        } else {
            // Generar username a partir del email (único)
            $username_base = sanitize_user( strstr( $email, '@', true ), true );
            if ( empty( $username_base ) ) {
                $username_base = sanitize_user( $first_name, true );
            }
            $username      = $username_base;
            $suffix        = 1;
            while ( username_exists( $username ) ) {
                $username = $username_base . $suffix;
                $suffix++;
            }
        }

        $user_id = wp_insert_user( array(
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => trim( $first_name . ' ' . $last_name ),
            'role'         => 'subscriber',
            'meta_input'   => array(
                'aura_phone' => $phone,
            ),
        ) );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array( 'message' => $user_id->get_error_message() ), 500 );
        }

        wp_send_json_success( array(
            'user_id'      => $user_id,
            'display_name' => trim( $first_name . ' ' . $last_name ),
            'email'        => $email,
            'redirect_url' => admin_url( 'admin.php?page=aura-permissions&user_id=' . $user_id . '&created=1' ),
        ) );
    }
}

// ============================================================
// Funciones helper globales — Identidad de la Organización
// ============================================================

/**
 * Retorna el nombre de la organización configurado.
 * Fallback: nombre del sitio WordPress.
 *
 * @return string
 */
function aura_get_org_name() {
    return get_option('aura_org_name', get_bloginfo('name'));
}

/**
 * Retorna la URL del logo de la organización.
 * Fallback: logo AURA por defecto.
 *
 * @param  string $size  thumbnail | medium | large | full
 * @return string
 */
function aura_get_org_logo_url( $size = 'medium' ) {
    $attachment_id = (int) get_option('aura_org_logo_id', 0);
    if ( $attachment_id ) {
        $src = wp_get_attachment_image_url( $attachment_id, $size );
        if ( $src ) {
            return $src;
        }
    }
    return AURA_PLUGIN_URL . 'assets/images/logo-aura.png';
}

/**
 * Retorna un tag <img> del logo de la organización listo para usar en templates.
 *
 * @param  string $class      Clase CSS adicional.
 * @param  string $max_height Altura máxima CSS (ej: '60px').
 * @return string  HTML tag <img>
 */
function aura_get_org_logo_img( $class = 'aura-org-logo', $max_height = '60px' ) {
    $url  = aura_get_org_logo_url('medium');
    $name = aura_get_org_name();
    return sprintf(
        '<img src="%s" alt="%s" class="%s" style="max-height:%s;width:auto;">',
        esc_url( $url ),
        esc_attr( $name ),
        esc_attr( $class ),
        esc_attr( $max_height )
    );
}

/**
 * Obtiene las URLs de la foto de un equipo en ambos tamaños registrados.
 *
 * Acepta tanto IDs de adjunto (numéricos) como URLs legacy.
 *
 * @param  string|int $photo  Attachment ID o URL directa (compatibilidad legacy).
 * @return array{full: string, thumb: string}
 */
function aura_get_equipment_photo_urls( $photo ) {
    if ( ! $photo ) return [ 'full' => '', 'thumb' => '' ];
    if ( is_numeric( $photo ) ) {
        $id        = (int) $photo;
        $full_src  = wp_get_attachment_image_src( $id, 'aura-equipment-full' );
        $thumb_src = wp_get_attachment_image_src( $id, 'aura-equipment-thumb' );
        $fallback  = wp_get_attachment_url( $id ) ?: '';
        return [
            'full'  => $full_src  ? $full_src[0]  : $fallback,
            'thumb' => $thumb_src ? $thumb_src[0] : ( $full_src ? $full_src[0] : $fallback ),
        ];
    }
    // URL legacy — se usa como es en ambos tamaños
    return [ 'full' => (string) $photo, 'thumb' => (string) $photo ];
}

/**
 * Iniciar el plugin
 */
function aura_business_suite() {
    return Aura_Business_Suite::get_instance();
}

// Iniciar el plugin
aura_business_suite();
