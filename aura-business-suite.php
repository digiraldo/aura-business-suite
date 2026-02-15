<?php
/**
 * Plugin Name: Aura Business Suite
 * Plugin URI: https://aurabusiness.com
 * Description: Suite modular de gestión empresarial con permisos granulares (CBAC) - Módulos: Finanzas, Vehículos, Formularios, Electricidad
 * Version: 1.0.0
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
define('AURA_VERSION', '1.0.0');
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
        // Módulos comunes
        require_once AURA_PLUGIN_DIR . 'modules/common/class-roles-manager.php';
        require_once AURA_PLUGIN_DIR . 'modules/common/class-notifications.php';
        
        // Módulo Financiero
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-cpt.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-categories-cpt.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-categories.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-categories-api.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-dashboard.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-charts.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-setup.php';
        require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-transactions.php';
        
        // Módulo de Vehículos
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-cpt.php';
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-alerts.php';
        require_once AURA_PLUGIN_DIR . 'modules/vehicles/class-vehicle-reports.php';
        
        // Módulo de Electricidad
        require_once AURA_PLUGIN_DIR . 'modules/electricity/class-electricity-cpt.php';
        require_once AURA_PLUGIN_DIR . 'modules/electricity/class-electricity-api.php';
        require_once AURA_PLUGIN_DIR . 'modules/electricity/class-electricity-dashboard.php';
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
        
        // Personalizar login - DESACTIVADO
        // add_action('login_enqueue_scripts', array($this, 'custom_login_logo'));
        // add_filter('login_headerurl', array($this, 'custom_login_logo_url'));
        // add_filter('login_headertext', array($this, 'custom_login_logo_url_title'));
        
        // Agregar favicon personalizado
        add_action('admin_head', array($this, 'custom_favicon'));
        add_action('wp_head', array($this, 'custom_favicon'));
        
        // Menú de administración
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Hooks AJAX para reinstalación de categorías
        add_action('wp_ajax_aura_reinstall_categories', array($this, 'ajax_reinstall_categories'));
    }
    
    /**
     * Inicializar módulos del plugin
     */
    public function init() {
        // Inicializar sistema de roles y capabilities
        Aura_Roles_Manager::init();
        
        // Inicializar CPTs de cada módulo
        Aura_Financial_CPT::init();
        Aura_Financial_Categories_CPT::init();
        Aura_Financial_Categories::get_instance();
        Aura_Vehicle_CPT::init();
        Aura_Electricity_CPT::init();
        
        // Inicializar REST API
        Aura_Financial_Categories_API::init();
        Aura_Electricity_API::init();
        
        // Inicializar sistema de notificaciones
        Aura_Notifications::init();
        
        // Inicializar alertas de vehículos
        Aura_Vehicle_Alerts::init();
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
    public function enqueue_admin_assets($hook) {
        // CSS global del admin
        wp_enqueue_style(
            'aura-admin-styles',
            AURA_PLUGIN_URL . 'assets/css/admin-styles.css',
            array(),
            AURA_VERSION
        );
        
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
     * Agregar favicon personalizado
     */
    public function custom_favicon() {
        $favicon_url = AURA_PLUGIN_URL . 'assets/images/logo-aura.png';
        echo '<link rel="icon" type="image/png" href="' . esc_url($favicon_url) . '">';
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        // Menú principal de Aura
        add_menu_page(
            __('Aura Suite', 'aura-suite'),                    // Título de la página
            __('Aura Suite', 'aura-suite'),                    // Título del menú
            'read',                                             // Capability mínima
            'aura-suite',                                       // Slug del menú
            array($this, 'render_main_dashboard'),             // Callback
            AURA_PLUGIN_URL . 'aura-icono.svg',               // Icono SVG
            3                                                   // Posición
        );
        
        // Dashboard principal
        add_submenu_page(
            'aura-suite',
            __('Dashboard', 'aura-suite'),
            __('Dashboard', 'aura-suite'),
            'read',
            'aura-suite',
            array($this, 'render_main_dashboard')
        );
        
        // Nueva Transacción (solo para usuarios con permisos de crear transacciones)
        if (current_user_can('aura_finance_create')) {
            add_submenu_page(
                'aura-suite',
                __('Nueva Transacción', 'aura-suite'),
                __('Nueva Transacción', 'aura-suite'),
                'aura_finance_create',
                'aura-financial-new-transaction',
                array($this, 'render_transaction_form')
            );
        }
        
        // Submenu de Configuración (solo para administradores)
        if (current_user_can('aura_admin_settings')) {
            add_submenu_page(
                'aura-suite',
                __('Configuración', 'aura-suite'),
                __('Configuración', 'aura-suite'),
                'aura_admin_settings',
                'aura-settings',
                array($this, 'render_settings_page')
            );
        }
        
        // Submenu de Gestión de Permisos (solo para administradores)
        if (current_user_can('aura_admin_permissions_assign')) {
            add_submenu_page(
                'aura-suite',
                __('Gestión de Permisos', 'aura-suite'),
                __('Permisos', 'aura-suite'),
                'aura_admin_permissions_assign',
                'aura-permissions',
                array($this, 'render_permissions_page')
            );
        }
    }
    
    /**
     * Renderizar dashboard principal
     */
    public function render_main_dashboard() {
        include AURA_PLUGIN_DIR . 'templates/main-dashboard.php';
    }
    
    /**
     * Renderizar formulario de nueva transacción
     */
    public function render_transaction_form() {
        include AURA_PLUGIN_DIR . 'templates/financial/transaction-form.php';
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

/**
 * Iniciar el plugin
 */
function aura_business_suite() {
    return Aura_Business_Suite::get_instance();
}

// Iniciar el plugin
aura_business_suite();
