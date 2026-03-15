<?php
/**
 * Plugin Name: Aura Business Suite
 * Plugin URI: https://aurabusiness.com
 * Description: Suite modular de gestión empresarial con permisos granulares (CBAC) - Módulos: Finanzas, Vehículos, Formularios, Electricidad, Áreas/Programas Multi-Usuario
 * Version: 1.1.0
 * Author: Aura Development Team
 * Author URI: https://aurabusiness.com
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
define('AURA_VERSION', '1.2.0');
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
        // Autoloader de Composer (PhpSpreadsheet, etc.)
        $autoload = AURA_PLUGIN_DIR . 'vendor/autoload.php';
        if ( file_exists( $autoload ) ) {
            require_once $autoload;
        }

        // Módulos comunes
        require_once AURA_PLUGIN_DIR . 'modules/common/class-roles-manager.php';
        require_once AURA_PLUGIN_DIR . 'modules/common/class-notifications.php';
        require_once AURA_PLUGIN_DIR . 'modules/common/class-google-calendar.php';
        
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
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-user-dashboard.php'; // Fase 6, Item 6.2
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-user-ledger.php';     // Fase 6, Item 6.3
        
        // Cargar WP_List_Table si estamos en admin
        if (is_admin()) {
            require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-transactions-list.php';
            require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-trash-list.php';
            require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-pending-list.php';
        }
        
        // Módulo de Áreas y Programas (Fase 7)
        require_once AURA_PLUGIN_DIR . 'modules/areas/class-areas-setup.php';
        require_once AURA_PLUGIN_DIR . 'modules/areas/class-areas-admin.php';  // Ítem 7.2 — Admin UI

        // Módulo de Vehículos
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-cpt.php';
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-alerts.php';
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-reports.php';
        
        // Módulo de Electricidad
        require_once AURA_PLUGIN_DIR . 'modules/electricity/class-electricity-cpt.php';
        require_once AURA_PLUGIN_DIR . 'modules/electricity/class-electricity-api.php';
        require_once AURA_PLUGIN_DIR . 'modules/electricity/class-electricity-dashboard.php';

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
        
        // Menú de administración
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Hooks AJAX para reinstalación de categorías
        add_action('wp_ajax_aura_reinstall_categories', array($this, 'ajax_reinstall_categories'));
        
        // Hook AJAX para guardar excepciones de categorías
        add_action('wp_ajax_aura_save_category_exceptions', array($this, 'ajax_save_category_exceptions'));

        // Hook AJAX para refresh de stats del dashboard principal
        add_action('wp_ajax_aura_dashboard_refresh_stats', array($this, 'ajax_dashboard_refresh_stats'));
    }
    
    /**
     * Inicializar módulos del plugin
     */
    public function init() {
        // Inicializar sistema de roles y capabilities
        Aura_Roles_Manager::init();
        
        Aura_Financial_Categories_CPT::init();
        Aura_Financial_Categories::get_instance();
        Aura_Vehicle_CPT::init();
        Aura_Electricity_CPT::init();
        
        // Inicializar REST API
        Aura_Financial_Categories_API::init();
        Aura_Electricity_API::init();
        
        // Inicializar sistema de notificaciones
        Aura_Notifications::init();
        Aura_Google_Calendar::init();
        
        // Inicializar alertas de vehículos
        Aura_Vehicle_Alerts::init();
        
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

        // Inicializar notificaciones y recordatorios (Fase 5, Item 5.4)
        Aura_Financial_Notifications::init();

        // Inicializar integraciones contables (Fase 5, Item 5.5)
        Aura_Financial_Integrations::init();

        // Inicializar Dashboard Financiero Personal del Usuario (Fase 6, Item 6.2)
        Aura_Financial_User_Dashboard::init();

        // Inicializar Libro Mayor por Usuario (Fase 6, Item 6.3)
        Aura_Financial_User_Ledger::init();

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
        Aura_Inventory_Setup::create_tables();
        
        // Instalar categorías financieras predeterminadas
        $this->install_default_categories();
        
        // Crear páginas necesarias si no existen
        $this->create_required_pages();
        
        // Flush rewrite rules para que funcionen los permalinks de CPTs
        flush_rewrite_rules();
        
        // Agregar opción de versión
        add_option('aura_version', AURA_VERSION);
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
        // CSS global del admin
        wp_enqueue_style(
            'aura-admin-styles',
            AURA_PLUGIN_URL . 'assets/css/admin-styles.css',
            array(),
            AURA_VERSION
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

        if ( $hook === 'finanzas_page_aura-financial-analytics' ) {
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
        if ( $hook === 'finanzas_page_aura-financial-reports' ) {
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
        if ( $hook === 'finanzas_page_aura-my-finance' ) {
            // jQuery UI Autocomplete para selector de usuario
            wp_enqueue_script( 'jquery-ui-autocomplete' );
            wp_enqueue_style( 'jquery-ui-autocomplete-style',
                'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css',
                [], '1.13.2'
            );
        }

        // Assets para Libro Mayor por Usuario (Fase 6, Item 6.3)
        if ( $hook === 'finanzas_page_aura-user-ledger' ) {
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
                array( 'jquery', 'chartjs' ),
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
            wp_enqueue_script(
                'aura-transactions-list',
                AURA_PLUGIN_URL . 'assets/js/transactions-list.js',
                array('jquery'),
                AURA_VERSION,
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
        if ($hook === 'finanzas_page_aura-financial-transactions') {
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
            wp_enqueue_style(
                'aura-transactions-list',
                AURA_PLUGIN_URL . 'assets/css/transactions-list.css',
                array(),
                AURA_VERSION
            );
            
            // JS del listado de transacciones
            wp_enqueue_script(
                'aura-transactions-list',
                AURA_PLUGIN_URL . 'assets/js/transactions-list.js',
                array('jquery', 'jquery-ui-datepicker', 'select2'),
                AURA_VERSION,
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
        if ($hook === 'finanzas_page_aura-financial-import') {
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
                'txt' => array(
                    'error_generic'   => __('Error al procesar la solicitud. Intente de nuevo.', 'aura-suite'),
                    'validating'      => __('Validando', 'aura-suite'),
                    'validate_btn'    => __('Validar datos', 'aura-suite'),
                    'map_required'    => __('Faltan campos obligatorios: Fecha, Tipo, Categoría y Monto deben estar mapeados.', 'aura-suite'),
                    'importing'       => __('Importando transacciones…', 'aura-suite'),
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
                    'ready_to_import' => __('Se importarán %d transacciones válidas.', 'aura-suite'),
                    'import_n'        => __('Importar %d transacciones', 'aura-suite'),
                    'confirm_rollback'=> __('¿Deshacer esta importación? Las transacciones importadas serán enviadas a la papelera.', 'aura-suite'),
                    'rollback_done'   => __('%d transacciones enviadas a la papelera.', 'aura-suite'),
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
                    'rollback_expired'=> __('Venció 24h', 'aura-suite'),
                    'auto_cat_note'   => __('Las categorías marcadas con ⚠ se crearán automáticamente si tiene esa opción habilitada.', 'aura-suite'),
                ),
            ));
        }

        // Assets específicos para presupuestos (Fase 5, Item 5.1)
        if ($hook === 'finanzas_page_aura-financial-budgets') {
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
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-budgets',
                AURA_PLUGIN_URL . 'assets/js/budgets.js',
                array('jquery', 'apexcharts'),
                AURA_VERSION,
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
                    'no_area'            => __('General', 'aura-suite'),
                    'area_subtotal'      => __('Subtotal', 'aura-suite'),
                    'h_tx_category'      => __('Categoría', 'aura-suite'),
                    'collapse_area'      => __('▲', 'aura-suite'),
                    'expand_area'        => __('▼', 'aura-suite'),
                ),
            ));
        }

        // Assets específicos para gestión de etiquetas (Fase 5, Item 5.2)
        if ($hook === 'finanzas_page_aura-financial-tags') {
            wp_enqueue_script('jquery-ui-autocomplete');
            wp_enqueue_style(
                'aura-tags-search',
                AURA_PLUGIN_URL . 'assets/css/tags-search.css',
                array('aura-admin-styles'),
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-tags',
                AURA_PLUGIN_URL . 'assets/js/tags.js',
                array('jquery', 'jquery-ui-autocomplete'),
                AURA_VERSION,
                true
            );
        }

        // Assets específicos para búsqueda avanzada (Fase 5, Item 5.2)
        if ($hook === 'finanzas_page_aura-financial-search') {
            wp_enqueue_script('jquery-ui-autocomplete');
            wp_enqueue_style(
                'aura-tags-search',
                AURA_PLUGIN_URL . 'assets/css/tags-search.css',
                array('aura-admin-styles'),
                AURA_VERSION
            );
            wp_enqueue_script(
                'aura-advanced-search',
                AURA_PLUGIN_URL . 'assets/js/advanced-search.js',
                array('jquery', 'jquery-ui-autocomplete'),
                AURA_VERSION,
                true
            );
        }

        // Assets específicos para auditoría y trazabilidad (Fase 5, Item 5.3)
        if ($hook === 'finanzas_page_aura-financial-audit') {
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
        if ($hook === 'finanzas_page_aura-financial-integrations') {
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
            || $hook === 'finanzas_page_aura-financial-new-transaction') {
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
            
            // Localizar script con datos para AJAX
            wp_localize_script('aura-transaction-edit', 'auraTransactionEdit', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aura_transaction_edit_nonce'),
                'transactionNonce' => wp_create_nonce('aura_transaction_nonce'),
                'labels' => array(
                    'date' => __('Fecha', 'aura-suite'),
                    'category' => __('Categoría', 'aura-suite'),
                    'amount' => __('Monto', 'aura-suite'),
                    'paymentMethod' => __('Método de Pago', 'aura-suite'),
                    'description' => __('Descripción', 'aura-suite'),
                    'reference' => __('Referencia', 'aura-suite'),
                    'recipient' => __('Destinatario/Pagador', 'aura-suite'),
                    'notes' => __('Notas', 'aura-suite'),
                    'tags' => __('Etiquetas', 'aura-suite'),
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
                    'significantAmountChange' => __('El cambio en el monto es significativo (%s%). Debes proporcionar un motivo.', 'aura-suite')
                ),
                'validation' => array(
                    'categoryRequired' => __('Debes seleccionar una categoría.', 'aura-suite'),
                    'amountRequired' => __('El monto debe ser mayor a 0.', 'aura-suite'),
                    'dateRequired' => __('La fecha es requerida.', 'aura-suite'),
                    'descriptionMinLength' => __('La descripción debe tener al menos 10 caracteres.', 'aura-suite'),
                    'changeReasonRequired' => __('Debes proporcionar un motivo del cambio (mínimo 20 caracteres).', 'aura-suite')
                )
            ));
        }
        
        // Assets específicos para la página de papelera de transacciones
        if ($hook === 'finanzas_page_aura-financial-trash') {
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
        if ($hook === 'finanzas_page_aura-financial-pending') {
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
            
            // CSS específico para aprobaciones pendientes
            wp_enqueue_style(
                'aura-pending-approvals',
                AURA_PLUGIN_URL . 'assets/css/pending-approvals.css',
                array('aura-transactions-list', 'aura-transaction-modal'),
                AURA_VERSION
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
            wp_enqueue_script(
                'aura-transactions-list',
                AURA_PLUGIN_URL . 'assets/js/transactions-list.js',
                array('jquery'),
                AURA_VERSION,
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
        
        // CSS personalizado para ícono del menú
        wp_add_inline_style('aura-admin-styles', '
            #adminmenu .toplevel_page_aura-suite .wp-menu-image {
                opacity: 1 !important;
            }
            #adminmenu .toplevel_page_aura-suite .wp-menu-image img {
                width: 20px !important;
                height: 20px !important;
                padding: 6px 0 !important;
                opacity: 0.85;
            }
            #adminmenu .toplevel_page_aura-suite:hover .wp-menu-image img,
            #adminmenu .toplevel_page_aura-suite.current .wp-menu-image img {
                opacity: 1;
            }
        ');
        
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
        
        // Localizar script con datos para AJAX
        wp_localize_script('aura-admin-scripts', 'auraData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('aura_nonce'),
            'strings' => array(
                'confirmDelete' => __('¿Estás seguro de eliminar este elemento?', 'aura-suite'),
                'error'         => __('Ha ocurrido un error. Por favor, intenta nuevamente.', 'aura-suite'),
            ),
        ));

        // Media Uploader para la página de Configuración (logo de la organización)
        if ( $hook === 'aura-suite_page_aura-settings' ) {
            wp_enqueue_media();
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

        // Gestión de Permisos (solo administradores)
        if (current_user_can('aura_admin_permissions_assign')) {
            add_submenu_page(
                'aura-suite',
                __('Gestión de Permisos', 'aura-suite'),
                __('🔐 Permisos', 'aura-suite'),
                'aura_admin_permissions_assign',
                'aura-permissions',
                array($this, 'render_permissions_page')
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
                4
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

            // ── GRUPO 2: Transacciones ───────────────────────────
            if (current_user_can('aura_finance_view_own') || current_user_can('aura_finance_view_all') || current_user_can('manage_options')) {
                add_submenu_page(
                    'aura-financial-dashboard',
                    __('Transacciones', 'aura-suite'),
                    __('Transacciones', 'aura-suite'),
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
                $pending_count = Aura_Financial_Approval::get_pending_count();
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
                $trash_count = Aura_Financial_Transactions_Delete::get_trash_count();
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
            if (current_user_can('aura_finance_view_all') || current_user_can('manage_options') || current_user_can('aura_areas_view_own')) {
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
            if (current_user_can('aura_finance_view_own') || current_user_can('aura_finance_view_all') || current_user_can('manage_options')) {
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
            if (current_user_can('aura_finance_create') || current_user_can('manage_options')) {
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
                4.5
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
