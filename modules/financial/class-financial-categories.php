<?php
/**
 * Gestión de Categorías Financieras - Interfaz Admin
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar WP_List_Table si no está disponible
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Clase para gestionar la interfaz de categorías financieras
 */
class Aura_Financial_Categories {
    
    /**
     * Instancia única (Singleton)
     */
    private static $instance = null;
    
    /**
     * Obtener instancia
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_aura_get_categories', array($this, 'ajax_get_categories'));
        add_action('wp_ajax_aura_create_category', array($this, 'ajax_create_category'));
        add_action('wp_ajax_aura_update_category', array($this, 'ajax_update_category'));
        add_action('wp_ajax_aura_delete_category', array($this, 'ajax_delete_category'));
        add_action('wp_ajax_aura_delete_all_categories', array($this, 'ajax_delete_all_categories'));
        add_action('wp_ajax_aura_reorder_categories', array($this, 'ajax_reorder_categories'));
        add_action('wp_ajax_aura_toggle_category_status', array($this, 'ajax_toggle_status'));
        add_action('wp_ajax_aura_get_category_by_id', array($this, 'ajax_get_category_by_id'));
        add_action('wp_ajax_aura_get_parent_categories', array($this, 'ajax_get_parent_categories'));
        add_action('wp_ajax_aura_get_subcategories', array($this, 'ajax_get_subcategories'));
        add_action('wp_ajax_aura_save_parent_category', array($this, 'ajax_save_parent_category'));
        add_action('wp_ajax_aura_delete_parent_category', array($this, 'ajax_delete_parent_category'));
        add_action('wp_ajax_aura_get_merge_preview', array($this, 'ajax_get_merge_preview'));
        add_action('wp_ajax_aura_merge_categories', array($this, 'ajax_merge_categories'));
        
        add_action('admin_init', array(__CLASS__, 'maybe_migrate_capex'));
    }

    /**
     * Helper universal para renderizar el HTML de un ícono o emoji de categoría.
     *
     * @param string $icon Nombre de Dashicon (ej: dashicons-money-alt) o caracter/emoji UTF-8 (ej: 💰).
     * @param string $color Color opcional en formato hexadecimal o CSS.
     * @param string $extra_class Clase CSS adicional.
     * @return string HTML seguro del ícono o emoji.
     */
    public static function render_icon_html( $icon, $color = '', $extra_class = '' ) {
        $icon = trim( (string) $icon );
        if ( empty( $icon ) ) {
            $icon = 'dashicons-category';
        }

        $style = ! empty( $color ) ? 'color:' . esc_attr( $color ) . ';' : '';

        if ( strpos( $icon, 'dashicons-' ) === 0 || strpos( $icon, 'dashicons' ) !== false ) {
            $dashicon_class = sanitize_html_class( $icon );
            return sprintf(
                '<span class="dashicons %s %s" style="%s vertical-align:middle;" aria-hidden="true"></span>',
                esc_attr( $dashicon_class ),
                esc_attr( $extra_class ),
                $style
            );
        }

        // Emoji o símbolo Unicode
        return sprintf(
            '<span class="aura-cat-emoji %s" style="%s line-height:1; vertical-align:middle; display:inline-flex; align-items:center; justify-content:center;" aria-hidden="true">%s</span>',
            esc_attr( $extra_class ),
            $style,
            esc_html( $icon )
        );
    }
    
    /**
     * Agregar página al menú de admin
     */
    public function add_admin_menu() {
        add_submenu_page(
            'aura-financial-dashboard',
            __('Gestión de Categorías', 'aura-suite'),
            __('Categorías', 'aura-suite'),
            'aura_finance_category_manage',
            'aura-financial-categories',
            array($this, 'render_categories_page')
        );

        // Subpágina oculta de Categorías Padre (no aparece en menú, accesible por enlace)
        add_submenu_page(
            null,
            __('Categorías Padre', 'aura-suite'),
            __('Categorías Padre', 'aura-suite'),
            'aura_finance_category_manage',
            'aura-financial-parent-categories',
            array($this, 'render_parent_categories_page')
        );

        // 🏗️ Subpágina de Gastos de Capital (CapEx) — visible en el menú
        add_submenu_page(
            'aura-financial-dashboard',
            __('Gastos de Capital (CapEx)', 'aura-suite'),
            __('💼 Gastos de Capital', 'aura-suite'),
            'aura_finance_view_own',
            'aura-capital-expenses',
            array($this, 'render_capital_expenses_page')
        );
    }
    
    /**
     * Renderizar página de categorías padre
     */
    public function render_parent_categories_page() {
        if (!current_user_can('aura_finance_category_manage')) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'aura-suite'));
        }

        // Mantener compatibilidad con URL legacy redirigiendo al hub unificado.
        $target = add_query_arg(
            array(
                'page' => 'aura-financial-categories',
                'tab'  => 'parents',
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($target);
        exit;
    }

    /**
     * Renderizar página de Gastos de Capital (CapEx)
     */
    public function render_capital_expenses_page() {
        if ( ! current_user_can('aura_finance_view_own') && ! current_user_can('manage_options') ) {
            wp_die( __('No tienes permisos para acceder a esta página.', 'aura-suite') );
        }
        // Asegurar que el módulo esté cargado
        if ( ! class_exists('Aura_Financial_Capital') ) {
            require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-capital.php';
        }
        require_once AURA_PLUGIN_DIR . 'templates/financial/capital-expenses-page.php';
    }

    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        $allowed_hooks = array(
            'finanzas_page_aura-financial-categories',
            'admin_page_aura-financial-parent-categories',
        );
        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }
        
        $css_file = AURA_PLUGIN_DIR . 'assets/css/financial-categories.css';
        $js_file  = AURA_PLUGIN_DIR . 'assets/js/financial-categories.js';
        $pjs_file = AURA_PLUGIN_DIR . 'assets/js/parent-categories.js';

        $css_ver  = file_exists($css_file) ? filemtime($css_file) : AURA_VERSION;
        $js_ver   = file_exists($js_file) ? filemtime($js_file) : AURA_VERSION;
        $pjs_ver  = file_exists($pjs_file) ? filemtime($pjs_file) : AURA_VERSION;

        // CSS
        wp_enqueue_style(
            'aura-financial-categories',
            AURA_PLUGIN_URL . 'assets/css/financial-categories.css',
            array(),
            $css_ver
        );
        
        // Color Picker de WordPress
        wp_enqueue_style('wp-color-picker');
        
        // JavaScript
        wp_enqueue_script(
            'aura-financial-categories',
            AURA_PLUGIN_URL . 'assets/js/financial-categories.js',
            array('jquery', 'wp-color-picker', 'aura-ui-core'),
            $js_ver,
            true
        );
        
        // Assets de árbol CRUD de categorías padre (ahora se usan también en el hub unificado).
        if (in_array($hook, array('finanzas_page_aura-financial-categories', 'admin_page_aura-financial-parent-categories'), true)) {
            // DataTables CDN (core + Responsive) — estándar del proyecto PRD §5.6
            wp_enqueue_style( 'datatables-css',
                'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css',
                [], '2.2.2' );
            wp_enqueue_style( 'datatables-responsive-css',
                'https://cdn.datatables.net/responsive/3.0.4/css/responsive.dataTables.min.css',
                [ 'datatables-css' ], '3.0.4' );
            wp_enqueue_script( 'datatables-js',
                'https://cdn.datatables.net/2.2.2/js/dataTables.min.js',
                [ 'jquery' ], '2.2.2', true );
            wp_enqueue_script( 'datatables-responsive-js',
                'https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js',
                [ 'datatables-js' ], '3.0.4', true );

            wp_enqueue_script(
                'aura-parent-categories',
                AURA_PLUGIN_URL . 'assets/js/parent-categories.js',
                array( 'jquery', 'wp-color-picker', 'datatables-responsive-js', 'aura-financial-categories' ),
                $pjs_ver,
                true
            );
        }

        // Localizar script
        wp_localize_script('aura-financial-categories', 'auraCategories', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('aura_categories_nonce'),
            'strings' => array(
                'confirmDelete'                 => __('¿Estás seguro de eliminar esta categoría? Esta acción no se puede deshacer.', 'aura-suite'),
                'confirmDeleteWithTransactions' => __('Esta categoría tiene %d transacción(es) asociada(s). No se puede eliminar. ¿Deseas desactivarla en su lugar?', 'aura-suite'),
                'confirmDeleteAll'              => __('⚠️ ATENCIÓN: Esta acción eliminará TODAS las categorías que no tengan transacciones asociadas. Las que sí tienen transacciones serán ignoradas. Esta operación NO se puede deshacer. ¿Deseas continuar?', 'aura-suite'),
                'confirmDeleteAllParents'       => __('⚠️ ATENCIÓN: Esto eliminará todas las categorías PADRE que no tengan subcategorías ni transacciones. ¿Continuar?', 'aura-suite'),
                'categoryCreated'               => __('Categoría creada exitosamente.', 'aura-suite'),
                'categoryUpdated'               => __('Categoría actualizada exitosamente.', 'aura-suite'),
                'categoryDeleted'               => __('Categoría eliminada exitosamente.', 'aura-suite'),
                'categoryDeactivated'           => __('Categoría desactivada exitosamente.', 'aura-suite'),
                'categoryActivated'             => __('Categoría activada exitosamente.', 'aura-suite'),
                'error'                         => __('Ocurrió un error. Por favor, intenta nuevamente.', 'aura-suite'),
                'nameRequired'                  => __('El nombre de la categoría es requerido.', 'aura-suite'),
                'invalidColor'                  => __('El color debe ser un código hexadecimal válido.', 'aura-suite'),
                'loading'                       => __('Cargando...', 'aura-suite'),
                'confirmMerge'                  => __('¿Estás seguro de que deseas fusionar estas categorías? Se reasignarán todas las transacciones históricas.', 'aura-suite'),
                'mergeSuccess'                  => __('Categorías fusionadas exitosamente.', 'aura-suite'),
                'selectBothCategories'          => __('Por favor selecciona tanto la categoría de origen como la de destino.', 'aura-suite'),
                'sameCategoryMerge'             => __('La categoría de origen y destino no pueden ser iguales.', 'aura-suite'),
            ),
        ));
    }
    
    /**
     * Renderizar página de categorías
     */
    public function render_categories_page() {
        // Verificar permisos
        if (!current_user_can('aura_finance_category_manage')) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'aura-suite'));
        }
        
        // Cargar template
        require_once AURA_PLUGIN_DIR . 'templates/financial/categories-page.php';
    }
    
    /**
     * AJAX: Obtener categorías
     */
    public function ajax_get_categories() {
        check_ajax_referer('aura_categories_nonce', 'nonce');
        
        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';
        
        $type      = isset($_POST['type'])      ? sanitize_text_field($_POST['type'])   : '';
        $status    = isset($_POST['status'])    ? sanitize_text_field($_POST['status']) : '';
        $search    = isset($_POST['search'])    ? sanitize_text_field($_POST['search']) : '';
        $parent_id = isset($_POST['parent_id']) ? $_POST['parent_id']                   : '';
        $orderby   = isset($_POST['orderby'])   ? sanitize_text_field($_POST['orderby']) : 'display_order';
        $order     = isset($_POST['order'])     ? sanitize_text_field($_POST['order'])   : 'ASC';
        
        // Construir condiciones WHERE
        $where = array('1=1');
        
        if (!empty($type) && in_array($type, array('income', 'expense', 'both'))) {
            $where[] = $wpdb->prepare("c.type = %s", $type);
        }
        
        if (!empty($status)) {
            $where[] = $wpdb->prepare("c.is_active = %d", $status === 'active' ? 1 : 0);
        }
        
        if ($parent_id === 'none') {
            // Solo categorías padre (sin padre)
            $where[] = "c.parent_id IS NULL";
        } elseif ($parent_id !== '' && is_numeric($parent_id) && intval($parent_id) > 0) {
            $where[] = $wpdb->prepare("c.parent_id = %d", intval($parent_id));
        }

        if (!empty($search)) {
            $where[] = $wpdb->prepare("(c.name LIKE %s OR c.description LIKE %s)", 
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        
        // Validar orderby
        $valid_orderby = array('name', 'type', 'display_order', 'created_at');
        if (!in_array($orderby, $valid_orderby)) {
            $orderby = 'display_order';
        }
        
        // Validar order
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        
        // Construir y ejecutar query
        $sql = "SELECT c.*, p.name as parent_name, p.color as parent_color
                FROM {$table} c 
                LEFT JOIN {$table} p ON c.parent_id = p.id 
                WHERE " . implode(' AND ', $where) . " 
                ORDER BY c.{$orderby} {$order}, c.name ASC";
        
        $results = $wpdb->get_results($sql);
        
        $categories = array();
        
        if ($results) {
            // Mapeo de conteo de transacciones por categoría (optimizado en 1 query)
            $tx_table = $wpdb->prefix . 'aura_finance_transactions';
            $tx_counts = array();
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$tx_table'") === $tx_table) {
                $has_expense_cat = (bool) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'expense_category_id'",
                        DB_NAME,
                        $tx_table
                    )
                );
                
                if ($has_expense_cat) {
                    $counts_raw = $wpdb->get_results(
                        "SELECT cat_id, COUNT(DISTINCT tx_id) as cnt FROM (
                            SELECT id as tx_id, category_id as cat_id FROM {$tx_table} 
                            WHERE deleted_at IS NULL AND category_id IS NOT NULL AND category_id > 0
                            UNION ALL
                            SELECT id as tx_id, expense_category_id as cat_id FROM {$tx_table} 
                            WHERE deleted_at IS NULL AND expense_category_id IS NOT NULL AND expense_category_id > 0
                        ) as combined_tx 
                        GROUP BY cat_id"
                    );
                } else {
                    $counts_raw = $wpdb->get_results(
                        "SELECT category_id as cat_id, COUNT(DISTINCT id) as cnt 
                         FROM {$tx_table} 
                         WHERE deleted_at IS NULL AND category_id IS NOT NULL AND category_id > 0
                         GROUP BY category_id"
                    );
                }
                
                if ($counts_raw) {
                    foreach ($counts_raw as $crow) {
                        $tx_counts[intval($crow->cat_id)] = intval($crow->cnt);
                    }
                }
            }

            foreach ($results as $row) {
                $cat_id = intval($row->id);
                $categories[] = array(
                    'id'                  => $cat_id,
                    'name'                => $row->name,
                    'slug'                => $row->slug,
                    'type'                => $row->type,
                    'parent_id'           => $row->parent_id ? intval($row->parent_id) : 0,
                    'parent_name'         => $row->parent_name ?: '',
                    'parent_color'        => $row->parent_color ?: '',
                    'color'               => $row->color ?: '#3498db',
                    'icon'                => $row->icon ?: 'dashicons-category',
                    'description'         => $row->description ?: '',
                    'is_active'           => (bool) $row->is_active,
                    'is_capex'            => isset($row->is_capex) ? (bool) $row->is_capex : false,
                    'integration_modules' => $row->integration_modules ? json_decode($row->integration_modules, true) : array(),
                    'display_order'       => intval($row->display_order),
                    'transaction_count'   => isset($tx_counts[$cat_id]) ? $tx_counts[$cat_id] : 0,
                );
            }
        }
        
        wp_send_json_success(array('categories' => $categories));
    }
    
    /**
     * AJAX: Obtener categoría por ID
     */
    public function ajax_get_category_by_id() {
        check_ajax_referer('aura_categories_nonce', 'nonce');
        
        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';
        
        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        
        if (!$category_id) {
            wp_send_json_error(array('message' => __('ID de categoría inválido.', 'aura-suite')));
        }
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $category_id
        ));
        
        if (!$row) {
            wp_send_json_error(array('message' => __('Categoría no encontrada.', 'aura-suite')));
        }
        
        $category = array(
            'id' => intval($row->id),
            'name' => $row->name,
            'slug' => $row->slug,
            'type' => $row->type,
            'parent_id' => intval($row->parent_id),
            'color' => $row->color ?: '#3498db',
            'icon' => $row->icon ?: 'dashicons-category',
            'description' => $row->description ?: '',
            'is_active' => (bool) $row->is_active,
            'is_capex' => isset($row->is_capex) ? (bool) $row->is_capex : false,
            'display_order' => intval($row->display_order),
            'integration_modules' => $row->integration_modules ? json_decode($row->integration_modules, true) : array(),
        );
        
        wp_send_json_success(array('category' => $category));
    }
    
    /**
     * AJAX: Crear categoría
     */
    public function ajax_create_category() {
        check_ajax_referer('aura_categories_nonce', 'nonce');
        
        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';
        
        // Validar y sanitizar datos
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $slug_input = isset($_POST['slug']) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'both';
        $parent_id_raw = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;
        $parent_id = $parent_id_raw > 0 ? $parent_id_raw : null;
        $parent_new_name = isset($_POST['parent_new_name']) ? sanitize_text_field( wp_unslash( $_POST['parent_new_name'] ) ) : '';
        $color = isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : '#3498db';
        $icon = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : 'dashicons-category';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $is_active = isset($_POST['is_active']) && in_array($_POST['is_active'], array('true', '1', 1, true), true) ? 1 : 0;
        $is_capex = isset($_POST['is_capex']) && in_array($_POST['is_capex'], array('true', '1', 1, true), true) ? 1 : 0;
        $display_order = isset($_POST['display_order']) ? absint($_POST['display_order']) : 0;
        
        // Procesar integraciones
        $integration_modules = array();
        if (isset($_POST['integration_modules'])) {
            $modules_json = stripslashes($_POST['integration_modules']);
            $integration_modules = json_decode($modules_json, true);
            if (!is_array($integration_modules)) {
                $integration_modules = array();
            }
        }
        
        // Validaciones
        if (empty($name)) {
            wp_send_json_error(array('message' => __('El nombre de la categoría es requerido.', 'aura-suite')));
        }
        
        if (!in_array($type, array('income', 'expense', 'both'))) {
            wp_send_json_error(array('message' => __('Tipo de categoría inválido.', 'aura-suite')));
        }

        // Crear categoría padre en línea (si no se seleccionó una existente)
        if ( empty( $parent_id ) && ! empty( $parent_new_name ) ) {
            $parent_id = $this->get_or_create_parent_category( $parent_new_name, $type );
        }
        
        if (!$color) {
            $color = '#3498db';
        }
        
        // Generar slug único (usa slug manual si viene informado)
        $slug = ! empty( $slug_input ) ? $slug_input : sanitize_title($name);
        $original_slug = $slug;
        $counter = 1;
        
        while ($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE slug = %s",
            $slug
        )) > 0) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        // Verificar jerarquía circular
        if ($parent_id > 0 && $this->would_create_circular_hierarchy(0, $parent_id)) {
            wp_send_json_error(array('message' => __('La categoría padre seleccionada crearía una jerarquía circular.', 'aura-suite')));
        }
        
        // Insertar categoría
        $data = array(
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'parent_id' => $parent_id,
            'color' => $color,
            'icon' => $icon,
            'description' => $description,
            'is_active' => $is_active,
            'is_capex' => $is_capex,
            'integration_modules' => !empty($integration_modules) ? wp_json_encode($integration_modules) : null,
            'display_order' => $display_order,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );
        
        $result = $wpdb->insert($table, $data, array(
            '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s'
        ));
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('No se pudo crear la categoría.', 'aura-suite')));
        }
        
        $category_id = $wpdb->insert_id;

        do_action( 'aura_finance_category_saved', $category_id, 'created', [] );
        wp_send_json_success(array(
            'message' => __('Categoría creada exitosamente.', 'aura-suite'),
            'category_id' => $category_id,
        ));
    }
    
    /**
     * AJAX: Actualizar categoría
     */
    public function ajax_update_category() {
        check_ajax_referer('aura_categories_nonce', 'nonce');
        
        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';
        
        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        
        if (!$category_id) {
            wp_send_json_error(array('message' => __('ID de categoría inválido.', 'aura-suite')));
        }
        
        // Verificar que la categoría existe
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $category_id
        ));
        
        if (!$existing) {
            wp_send_json_error(array('message' => __('Categoría no encontrada.', 'aura-suite')));
        }
        
        // Validar y sanitizar datos
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $slug_input = isset($_POST['slug']) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'both';
        $parent_id_raw = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;
        $parent_id = $parent_id_raw > 0 ? $parent_id_raw : null;
        $parent_new_name = isset($_POST['parent_new_name']) ? sanitize_text_field( wp_unslash( $_POST['parent_new_name'] ) ) : '';
        $color = isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : '#3498db';
        $icon = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : 'dashicons-category';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $is_active = isset($_POST['is_active']) && in_array($_POST['is_active'], array('true', '1', 1, true), true) ? 1 : 0;
        $is_capex = isset($_POST['is_capex']) && in_array($_POST['is_capex'], array('true', '1', 1, true), true) ? 1 : 0;
        $display_order = isset($_POST['display_order']) ? absint($_POST['display_order']) : 0;
        
        // Procesar integraciones
        $integration_modules = array();
        if (isset($_POST['integration_modules'])) {
            $modules_json = stripslashes($_POST['integration_modules']);
            $integration_modules = json_decode($modules_json, true);
            if (!is_array($integration_modules)) {
                $integration_modules = array();
            }
        }
        
        // Validaciones
        if (empty($name)) {
            wp_send_json_error(array('message' => __('El nombre de la categoría es requerido.', 'aura-suite')));
        }
        
        if (!in_array($type, array('income', 'expense', 'both'))) {
            wp_send_json_error(array('message' => __('Tipo de categoría inválido.', 'aura-suite')));
        }

        // Crear/usar categoría padre escrita manualmente (si no se eligió una existente)
        if ( empty( $parent_id ) && ! empty( $parent_new_name ) ) {
            $parent_id = $this->get_or_create_parent_category( $parent_new_name, $type );
        }
        
        if (!$color) {
            $color = '#3498db';
        }
        
        // Verificar jerarquía circular antes de actualizar
        if ($parent_id > 0 && $this->would_create_circular_hierarchy($category_id, $parent_id)) {
            wp_send_json_error(array('message' => __('La categoría padre seleccionada crearía una jerarquía circular.', 'aura-suite')));
        }
        
        // Resolver slug (manual o automático por nombre) manteniendo unicidad
        $slug = $existing->slug;
        if ( ! empty( $slug_input ) ) {
            $slug = $slug_input;
        } elseif ($name !== $existing->name) {
            $slug = sanitize_title($name);
        }

        if ( $slug !== $existing->slug ) {
            $original_slug = $slug;
            $counter = 1;
            
            while ($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id != %d",
                $slug,
                $category_id
            )) > 0) {
                $slug = $original_slug . '-' . $counter;
                $counter++;
            }
        }
        
        // Actualizar categoría
        $data = array(
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'parent_id' => $parent_id,
            'color' => $color,
            'icon' => $icon,
            'description' => $description,
            'is_active' => $is_active,
            'is_capex' => $is_capex,
            'integration_modules' => !empty($integration_modules) ? wp_json_encode($integration_modules) : null,
            'display_order' => $display_order,
            'updated_at' => current_time('mysql'),
        );
        
        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $category_id),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('No se pudo actualizar la categoría.', 'aura-suite')));
        }

        do_action( 'aura_finance_category_saved', $category_id, 'updated', $data );
        wp_send_json_success(array(
            'message' => __('Categoría actualizada exitosamente.', 'aura-suite'),
        ));
    }
    
    /**
     * AJAX: Eliminar categoría
     */
    public function ajax_delete_category() {
        check_ajax_referer('aura_categories_nonce', 'nonce');
        
        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';
        
        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        
        if (!$category_id) {
            wp_send_json_error(array('message' => __('ID de categoría inválido.', 'aura-suite')));
        }
        
        // Verificar que la categoría existe
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $category_id
        ));
        
        if (!$existing) {
            wp_send_json_error(array('message' => __('Categoría no encontrada.', 'aura-suite')));
        }
        
        // Verificar si tiene transacciones asociadas
        $transaction_count = $this->get_transaction_count($category_id);
        
        if ($transaction_count > 0) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Esta categoría tiene %d transacción(es) asociada(s) y no puede ser eliminada.', 'aura-suite'),
                    $transaction_count
                ),
                'transaction_count' => $transaction_count,
            ));
        }
        
        // Verificar si tiene subcategorías
        $subcategories = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE parent_id = %d",
            $category_id
        ));
        
        if ($subcategories > 0) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Esta categoría tiene %d subcategoría(s) asociada(s). Elimine o reasigne las subcategorías primero.', 'aura-suite'),
                    $subcategories
                ),
            ));
        }
        
        // Eliminar categoría
        $result = $wpdb->delete($table, array('id' => $category_id), array('%d'));
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('No se pudo eliminar la categoría.', 'aura-suite')));
        }

        do_action( 'aura_finance_category_deleted', $category_id );
        wp_send_json_success(array(
            'message' => __('Categoría eliminada exitosamente.', 'aura-suite'),
        ));
    }
    
    /**
     * AJAX: Toggle status de categoría
     */
    public function ajax_toggle_status() {
        check_ajax_referer('aura_categories_nonce', 'nonce');
        
        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';
        
        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        
        if (!$category_id) {
            wp_send_json_error(array('message' => __('ID de categoría inválido.', 'aura-suite')));
        }
        
        // Verificar que la categoría existe
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $category_id
        ));
        
        if (!$existing) {
            wp_send_json_error(array('message' => __('Categoría no encontrada.', 'aura-suite')));
        }
        
        $current_status = (bool) $existing->is_active;
        $new_status = !$current_status ? 1 : 0;
        
        $result = $wpdb->update(
            $table,
            array(
                'is_active' => $new_status,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $category_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('No se pudo actualizar el estado.', 'aura-suite')));
        }
        
        $message = $new_status === 1
            ? __('Categoría activada exitosamente.', 'aura-suite')
            : __('Categoría desactivada exitosamente.', 'aura-suite');
        
        wp_send_json_success(array(
            'message' => $message,
            'new_status' => (bool) $new_status,
        ));
    }
    
    /**
     * Obtener cantidad de transacciones
     */
    private function get_transaction_count($category_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aura_finance_transactions';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return 0;
        }

        $has_expense_cat = (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'expense_category_id'",
                DB_NAME,
                $table_name
            )
        );

        if ($has_expense_cat) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT id) FROM {$table_name} 
                 WHERE (category_id = %d OR expense_category_id = %d) 
                 AND deleted_at IS NULL",
                $category_id,
                $category_id
            ));
        } else {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT id) FROM {$table_name} 
                 WHERE category_id = %d 
                 AND deleted_at IS NULL",
                $category_id
            ));
        }
        
        return intval($count);
    }
    
    /**
     * Verificar si crearía jerarquía circular
     */
    private function would_create_circular_hierarchy($post_id, $parent_id) {
        if ($parent_id == 0 || $parent_id == $post_id) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';
        
        $current_parent = $parent_id;
        $max_depth = 10;
        $depth = 0;
        
        while ($current_parent > 0 && $depth < $max_depth) {
            if ($current_parent == $post_id) {
                return true;
            }
            
            $parent = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $current_parent
            ));
            
            if (!$parent) {
                break;
            }
            
            $current_parent = intval($parent->parent_id);
            $depth++;
        }
        
        return false;
    }

    /**
     * Obtiene o crea una categoría principal para usarla como padre.
     */
    private function get_or_create_parent_category( $parent_name, $child_type = 'both' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        $parent_name = sanitize_text_field( $parent_name );
        if ( $parent_name === '' ) {
            return null;
        }

        // Reusar si ya existe una categoría principal con ese nombre.
        $existing_parent_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE name = %s AND parent_id IS NULL LIMIT 1",
                $parent_name
            )
        );

        if ( ! empty( $existing_parent_id ) ) {
            return (int) $existing_parent_id;
        }

        $base_slug = sanitize_title( $parent_name );
        $slug = $base_slug;
        $counter = 1;
        while ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug = %s", $slug ) ) > 0 ) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }

        $parent_type = in_array( $child_type, array( 'income', 'expense', 'both' ), true ) ? $child_type : 'both';

        $wpdb->insert(
            $table,
            array(
                'name' => $parent_name,
                'slug' => $slug,
                'type' => $parent_type,
                'parent_id' => null,
                'color' => '#64748b',
                'icon' => 'dashicons-category',
                'description' => __('Categoría padre creada automáticamente desde el modal.', 'aura-suite'),
                'is_active' => 1,
                'display_order' => 0,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * AJAX: Obtener sólo categorías padre (parent_id IS NULL — FK autorreferenciada)
     */
    public function ajax_get_parent_categories() {
        check_ajax_referer('aura_categories_nonce', 'nonce');

        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $where = "parent_id IS NULL AND (SELECT COUNT(*) FROM {$table} s WHERE s.parent_id = c.id) > 0";
        if (!empty($search)) {
            $where .= $wpdb->prepare(" AND (name LIKE %s OR description LIKE %s)",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }

        $rows = $wpdb->get_results(
            "SELECT c.*,
                (SELECT COUNT(*) FROM {$table} s WHERE s.parent_id = c.id) AS subcategory_count
             FROM {$table} c
             WHERE {$where}
             ORDER BY c.display_order ASC, c.name ASC"
        );

        $parents = array();
        foreach ((array) $rows as $row) {
            $parents[] = array(
                'id'               => intval($row->id),
                'name'             => $row->name,
                'slug'             => $row->slug,
                'type'             => $row->type,
                'color'            => $row->color ?: '#3498db',
                'icon'             => $row->icon ?: 'dashicons-category',
                'description'      => $row->description ?: '',
                'is_active'        => (bool) $row->is_active,
                'display_order'    => intval($row->display_order),
                'subcategory_count'=> intval($row->subcategory_count),
            );
        }

        wp_send_json_success(array('parents' => $parents));
    }

    /**
     * 🆕 AJAX: Obtener subcategorías de una categoría padre
     */
    public function ajax_get_subcategories() {
        check_ajax_referer('aura_categories_nonce', 'nonce');

        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }

        $parent_id = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;

        if (empty($parent_id)) {
            wp_send_json_error(array('message' => __('ID de categoría padre inválido.', 'aura-suite')));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        // Verificar que la categoría padre existe y es efectivamente padre
        $parent = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE id = %d AND parent_id IS NULL",
            $parent_id
        ));

        if (!$parent) {
            wp_send_json_error(array('message' => __('Categoría padre no encontrada.', 'aura-suite')));
        }

        // Obtener subcategorías ordenadas
        $subcategories = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, slug, type, icon, color, is_active, display_order
             FROM {$table}
             WHERE parent_id = %d
             ORDER BY display_order ASC, name ASC",
            $parent_id
        ));

        $results = array();
        foreach ((array) $subcategories as $subcat) {
            $results[] = array(
                'id'           => intval($subcat->id),
                'name'         => $subcat->name,
                'slug'         => $subcat->slug,
                'type'         => $subcat->type,
                'icon'         => $subcat->icon ?: 'dashicons-tag',
                'color'        => $subcat->color ?: '#3498db',
                'is_active'    => (bool) $subcat->is_active,
                'display_order'=> intval($subcat->display_order),
            );
        }

        wp_send_json_success(array('subcategories' => $results));
    }

    /**
     * AJAX: Crear o actualizar categoría padre
     */
    public function ajax_save_parent_category() {
        check_ajax_referer('aura_categories_nonce', 'nonce');

        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        $category_id  = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        $name         = isset($_POST['name'])         ? sanitize_text_field($_POST['name'])     : '';
        $slug_input   = isset($_POST['slug'])         ? sanitize_title(wp_unslash($_POST['slug'])) : '';
        $type         = isset($_POST['type'])         ? sanitize_text_field($_POST['type'])     : 'both';
        $color        = isset($_POST['color'])        ? sanitize_hex_color($_POST['color'])     : '#3498db';
        $icon         = isset($_POST['icon'])         ? sanitize_text_field($_POST['icon'])     : 'dashicons-category';
        $description  = isset($_POST['description'])  ? sanitize_textarea_field($_POST['description']) : '';
        $is_active    = (isset($_POST['is_active']) && $_POST['is_active'] === 'true') ? 1 : 0;
        $display_order = isset($_POST['display_order']) ? absint($_POST['display_order']) : 0;

        if (empty($name)) {
            wp_send_json_error(array('message' => __('El nombre es requerido.', 'aura-suite')));
        }

        if (!in_array($type, array('income', 'expense', 'both'), true)) {
            wp_send_json_error(array('message' => __('Tipo inválido.', 'aura-suite')));
        }

        if (!$color) { $color = '#3498db'; }

        // Slug único
        $slug = !empty($slug_input) ? $slug_input : sanitize_title($name);
        $original_slug = $slug;
        $counter = 1;
        while ($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id != %d",
            $slug, $category_id
        )) > 0) {
            $slug = $original_slug . '-' . $counter++;
        }

        $data = array(
            'name'          => $name,
            'slug'          => $slug,
            'type'          => $type,
            'parent_id'     => null,
            'color'         => $color,
            'icon'          => $icon,
            'description'   => $description,
            'is_active'     => $is_active,
            'display_order' => $display_order,
            'updated_at'    => current_time('mysql'),
        );
        $formats = array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s');

        if ($category_id) {
            $existing = $wpdb->get_row($wpdb->prepare("SELECT id, parent_id FROM {$table} WHERE id = %d", $category_id));
            if (!$existing) {
                wp_send_json_error(array('message' => __('Categoría no encontrada.', 'aura-suite')));
            }
            if (!empty($existing->parent_id)) {
                wp_send_json_error(array('message' => __('Esta categoría no es una categoría padre.', 'aura-suite')));
            }
            $result = $wpdb->update($table, $data, array('id' => $category_id), $formats, array('%d'));
            $msg = __('Categoría padre actualizada.', 'aura-suite');
        } else {
            $data['created_at'] = current_time('mysql');
            $formats[] = '%s';
            $result = $wpdb->insert($table, $data, $formats);
            $category_id = $wpdb->insert_id;
            $msg = __('Categoría padre creada.', 'aura-suite');
        }

        if ($result === false) {
            wp_send_json_error(array('message' => __('No se pudo guardar la categoría padre.', 'aura-suite')));
        }

        wp_send_json_success(array('message' => $msg, 'category_id' => $category_id));
    }

    /**
     * AJAX: Eliminar categoría padre (solo si no tiene subcategorías)
     */
    public function ajax_delete_parent_category() {
        check_ajax_referer('aura_categories_nonce', 'nonce');

        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        if (!$category_id) {
            wp_send_json_error(array('message' => __('ID inválido.', 'aura-suite')));
        }

        $existing = $wpdb->get_row($wpdb->prepare("SELECT id, parent_id FROM {$table} WHERE id = %d", $category_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Categoría no encontrada.', 'aura-suite')));
        }
        if (!empty($existing->parent_id)) {
            wp_send_json_error(array('message' => __('Esta categoría no es una categoría padre.', 'aura-suite')));
        }

        $sub_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE parent_id = %d", $category_id
        ));
        if ($sub_count > 0) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('No se puede eliminar: tiene %d subcategoría(s). Reasígnalas primero.', 'aura-suite'),
                    $sub_count
                )
            ));
        }

        $tx_count = $this->get_transaction_count($category_id);
        if ($tx_count > 0) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('No se puede eliminar: tiene %d transacción(es) asociada(s). Desactívala en su lugar.', 'aura-suite'),
                    $tx_count
                )
            ));
        }

        $result = $wpdb->delete($table, array('id' => $category_id), array('%d'));
        if ($result === false) {
            wp_send_json_error(array('message' => __('No se pudo eliminar.', 'aura-suite')));
        }

        wp_send_json_success(array('message' => __('Categoría padre eliminada.', 'aura-suite')));
    }

    /**
     * AJAX: Eliminar TODAS las categorías (o solo las padre) sin transacciones ni subcategorías.
     * Scope: 'all' = todas | 'parents_only' = solo padres.
     */
    public function ajax_delete_all_categories() {
        check_ajax_referer('aura_categories_nonce', 'nonce');

        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }

        $scope = isset($_POST['scope']) ? sanitize_text_field($_POST['scope']) : 'all';

        global $wpdb;
        $table      = $wpdb->prefix . 'aura_finance_categories';
        $tx_table   = $wpdb->prefix . 'aura_finance_transactions';
        $tx_exists  = $wpdb->get_var("SHOW TABLES LIKE '{$tx_table}'") === $tx_table;

        // Obtener IDs candidatos
        if ($scope === 'parents_only') {
            $candidates = $wpdb->get_col("SELECT id FROM {$table} WHERE parent_id IS NULL ORDER BY id ASC");
        } else {
            $candidates = $wpdb->get_col("SELECT id FROM {$table} ORDER BY id ASC");
        }

        $deleted  = 0;
        $skipped  = 0;
        $messages = array();

        foreach ($candidates as $cat_id) {
            $cat_id = intval($cat_id);

            // Verificar transacciones
            if ($tx_exists) {
                $tx_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tx_table} WHERE category_id = %d AND deleted_at IS NULL",
                    $cat_id
                ));
                if ($tx_count > 0) {
                    $skipped++;
                    continue;
                }
            }

            // Verificar subcategorías (no eliminar padre que aún tenga hijos)
            $sub_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE parent_id = %d",
                $cat_id
            ));
            if ($sub_count > 0) {
                $skipped++;
                continue;
            }

            $result = $wpdb->delete($table, array('id' => $cat_id), array('%d'));
            if ($result !== false) {
                $deleted++;
            }
        }

        $msg = sprintf(
            _n('%d categoría eliminada.', '%d categorías eliminadas.', $deleted, 'aura-suite'),
            $deleted
        );

        if ($skipped > 0) {
            $msg .= ' ' . sprintf(
                _n('%d omitida (tiene transacciones o subcategorías).', '%d omitidas (tienen transacciones o subcategorías).', $skipped, 'aura-suite'),
                $skipped
            );
        }

        wp_send_json_success(array(
            'message' => $msg,
            'deleted' => $deleted,
            'skipped' => $skipped,
        ));
    }

    /**
     * Migración Fase 8.x: agregar columna is_capex a la tabla de categorías.
     */
    public static function maybe_migrate_capex() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        // Evitar error si la tabla no existe aún.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return;
        }

        $columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );

        if ( ! in_array( 'is_capex', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN is_capex TINYINT(1) NOT NULL DEFAULT 0 AFTER type" );
            if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log( 'AURA: Columna is_capex agregada a ' . $table ); }
        }
    }

    /**
     * Reordenar y anidar categorías vía Drag & Drop
     */
    public function ajax_reorder_categories() {
        check_ajax_referer('aura_categories_nonce', 'nonce');

        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }

        $hierarchy = isset($_POST['hierarchy']) ? $_POST['hierarchy'] : array();
        if (empty($hierarchy) || !is_array($hierarchy)) {
            wp_send_json_error(array('message' => __('Estructura de jerarquía inválida.', 'aura-suite')));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        $wpdb->query('START TRANSACTION');

        foreach ($hierarchy as $item) {
            $id        = isset($item['id']) ? absint($item['id']) : 0;
            $parent_id = (!empty($item['parent_id']) && $item['parent_id'] !== 'null') ? absint($item['parent_id']) : null;
            $order     = isset($item['display_order']) ? absint($item['display_order']) : 0;

            if (!$id) {
                continue;
            }

            if ($parent_id !== null) {
                if ($parent_id === $id || $this->would_create_circular_hierarchy($id, $parent_id)) {
                    $wpdb->query('ROLLBACK');
                    wp_send_json_error(array(
                        'message' => __('Conflicto: No puedes anidar una categoría dentro de sus propias subcategorías.', 'aura-suite')
                    ));
                }
            }

            if ($parent_id === null || $parent_id <= 0) {
                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET parent_id = NULL, display_order = %d, updated_at = %s WHERE id = %d",
                    $order,
                    current_time('mysql'),
                    $id
                ));
            } else {
                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET parent_id = %d, display_order = %d, updated_at = %s WHERE id = %d",
                    $parent_id,
                    $order,
                    current_time('mysql'),
                    $id
                ));
            }

            if ($updated === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(array('message' => __('Error al guardar la nueva jerarquía en base de datos.', 'aura-suite')));
            }
        }

        $wpdb->query('COMMIT');
        wp_send_json_success(array('message' => __('Jerarquía y orden actualizados correctamente.', 'aura-suite')));
    }

    /**
     * AJAX: Obtener vista previa de impacto de fusión entre dos categorías
     */
    public function ajax_get_merge_preview() {
        check_ajax_referer('aura_categories_nonce', 'nonce');

        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        $source_id = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
        $target_id = isset($_POST['target_id']) ? absint($_POST['target_id']) : 0;

        if (!$source_id) {
            wp_send_json_error(array('message' => __('ID de categoría origen inválido.', 'aura-suite')));
        }

        $source = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $source_id));
        if (!$source) {
            wp_send_json_error(array('message' => __('Categoría de origen no encontrada.', 'aura-suite')));
        }

        $source_tx_count     = $this->get_transaction_count($source_id);
        $source_subcat_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE parent_id = %d", $source_id));
        
        $budget_table = $wpdb->prefix . 'aura_finance_budgets';
        $source_budget_count = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$budget_table}'") === $budget_table) {
            $source_budget_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$budget_table} WHERE category_id = %d", $source_id));
        }

        $response_data = array(
            'source' => array(
                'id'           => (int) $source->id,
                'name'         => $source->name,
                'color'        => $source->color,
                'icon'         => $source->icon,
                'type'         => $source->type,
                'parent_id'    => (int) $source->parent_id,
                'tx_count'     => $source_tx_count,
                'subcat_count' => $source_subcat_count,
                'budget_count' => $source_budget_count,
            ),
            'target' => null,
        );

        if ($target_id > 0) {
            if ($target_id === $source_id) {
                wp_send_json_error(array('message' => __('No puedes fusionar una categoría consigo misma.', 'aura-suite')));
            }

            $target = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $target_id));
            if (!$target) {
                wp_send_json_error(array('message' => __('Categoría destino no encontrada.', 'aura-suite')));
            }

            $target_tx_count     = $this->get_transaction_count($target_id);
            $target_subcat_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE parent_id = %d", $target_id));
            $is_circular         = $this->would_create_circular_hierarchy($target_id, $source_id);

            $response_data['target'] = array(
                'id'              => (int) $target->id,
                'name'            => $target->name,
                'color'           => $target->color,
                'icon'            => $target->icon,
                'type'            => $target->type,
                'parent_id'       => (int) $target->parent_id,
                'tx_count'        => $target_tx_count,
                'subcat_count'    => $target_subcat_count,
                'projected_total' => $source_tx_count + $target_tx_count,
                'is_circular'     => $is_circular,
            );
        }

        wp_send_json_success($response_data);
    }

    /**
     * AJAX: Ejecutar Fusión Atómica de Categorías
     */
    public function ajax_merge_categories() {
        check_ajax_referer('aura_categories_nonce', 'nonce');

        if (!current_user_can('aura_finance_category_manage')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes.', 'aura-suite')));
        }

        global $wpdb;
        $cat_table    = $wpdb->prefix . 'aura_finance_categories';
        $tx_table     = $wpdb->prefix . 'aura_finance_transactions';
        $budget_table = $wpdb->prefix . 'aura_finance_budgets';
        $recur_table  = $wpdb->prefix . 'aura_finance_recurring_transactions';

        $source_id         = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
        $target_id         = isset($_POST['target_id']) ? absint($_POST['target_id']) : 0;
        $post_action       = isset($_POST['post_action']) ? sanitize_key($_POST['post_action']) : 'archive';
        $reassign_children = !empty($_POST['reassign_children']) && $_POST['reassign_children'] !== 'false';

        if (!$source_id || !$target_id) {
            wp_send_json_error(array('message' => __('Debes seleccionar una categoría de origen y una de destino.', 'aura-suite')));
        }

        if ($source_id === $target_id) {
            wp_send_json_error(array('message' => __('La categoría de origen y destino no pueden ser la misma.', 'aura-suite')));
        }

        $source = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$cat_table} WHERE id = %d", $source_id));
        $target = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$cat_table} WHERE id = %d", $target_id));

        if (!$source || !$target) {
            wp_send_json_error(array('message' => __('Categoría origen o destino no encontrada.', 'aura-suite')));
        }

        if ($reassign_children && $this->would_create_circular_hierarchy($target_id, $source_id)) {
            wp_send_json_error(array('message' => __('La categoría destino es una subcategoría de la categoría origen. No se pueden mover las subcategorías sin crear un ciclo.', 'aura-suite')));
        }

        // Inicio de Transacción SQL Atómica
        $wpdb->query('START TRANSACTION');

        try {
            // 1. Reasignar transacciones existentes
            if ($wpdb->get_var("SHOW TABLES LIKE '{$tx_table}'") === $tx_table) {
                // Verificar si existe la columna expense_category_id
                $has_expense_cat = (bool) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'expense_category_id'",
                        DB_NAME,
                        $tx_table
                    )
                );

                // Actualizar category_id
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$tx_table} SET category_id = %d WHERE category_id = %d",
                    $target_id,
                    $source_id
                ));

                // Actualizar expense_category_id si existe
                if ($has_expense_cat) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$tx_table} SET expense_category_id = %d WHERE expense_category_id = %d",
                        $target_id,
                        $source_id
                    ));
                }
            }

            // 2. Reasignar subcategorías hijas si se solicitó
            $subcats_migrated = 0;
            if ($reassign_children) {
                $subcats_migrated = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$cat_table} WHERE parent_id = %d", $source_id));
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$cat_table} SET parent_id = %d, updated_at = %s WHERE parent_id = %d",
                    $target_id,
                    current_time('mysql'),
                    $source_id
                ));
            }

            // 3. Reasignar presupuestos si existen
            if ($wpdb->get_var("SHOW TABLES LIKE '{$budget_table}'") === $budget_table) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$budget_table} SET category_id = %d WHERE category_id = %d",
                    $target_id,
                    $source_id
                ));
            }

            // 4. Reasignar transacciones recurrentes si existen
            if ($wpdb->get_var("SHOW TABLES LIKE '{$recur_table}'") === $recur_table) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$recur_table} SET category_id = %d WHERE category_id = %d",
                    $target_id,
                    $source_id
                ));
            }

            // 5. Acción Post-Fusión sobre la Categoría Origen
            if ($post_action === 'delete') {
                // Verificar que no le queden subcategorías sin migrar
                $remaining_sub = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$cat_table} WHERE parent_id = %d", $source_id));
                if ($remaining_sub > 0) {
                    // Convertir subcategorías restantes a raíz antes de eliminar
                    $wpdb->query($wpdb->prepare("UPDATE {$cat_table} SET parent_id = NULL WHERE parent_id = %d", $source_id));
                }
                $wpdb->delete($cat_table, array('id' => $source_id), array('%d'));
                do_action('aura_finance_category_deleted', $source_id);
            } elseif ($post_action === 'archive') {
                $wpdb->update(
                    $cat_table,
                    array(
                        'is_active'   => 0,
                        'updated_at'  => current_time('mysql'),
                    ),
                    array('id' => $source_id),
                    array('%d', '%s'),
                    array('%d')
                );
                do_action('aura_finance_category_saved', $source_id, 'updated', array('is_active' => 0));
            } else {
                // 'keep': Conservar vacía pero actualizar timestamp
                $wpdb->update(
                    $cat_table,
                    array('updated_at' => current_time('mysql')),
                    array('id' => $source_id),
                    array('%s'),
                    array('%d')
                );
            }

            // 6. Auditoría y Hooks
            $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$history_table}'") === $history_table) {
                $current_user_id = get_current_user_id();
                $wpdb->insert(
                    $history_table,
                    array(
                        'transaction_id' => 0, // Evento a nivel de categoría
                        'field_changed'  => 'category_merged',
                        'old_value'      => sprintf('Cat ID: %d (%s)', $source_id, $source->name),
                        'new_value'      => sprintf('Cat ID: %d (%s)', $target_id, $target->name),
                        'change_reason'  => sprintf(__('Fusión de categoría completada con post-acción: %s', 'aura-suite'), $post_action),
                        'changed_by'     => $current_user_id,
                        'changed_at'     => current_time('mysql'),
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
                );
            }

            do_action('aura_finance_categories_merged', $source_id, $target_id, $post_action);

            $wpdb->query('COMMIT');

            wp_send_json_success(array(
                'message'           => sprintf(
                    __('¡Fusión exitosa! La categoría "%s" fue consolidada en "%s".', 'aura-suite'),
                    $source->name,
                    $target->name
                ),
                'source_id'         => $source_id,
                'target_id'         => $target_id,
                'post_action'       => $post_action,
                'subcats_migrated'  => $subcats_migrated,
            ));

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array(
                'message' => __('Ocurrió un error inesperado al procesar la fusión: ', 'aura-suite') . $e->getMessage()
            ));
        }
    }

}
