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
        add_action('wp_ajax_aura_toggle_category_status', array($this, 'ajax_toggle_status'));
        add_action('wp_ajax_aura_get_category_by_id', array($this, 'ajax_get_category_by_id'));
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
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        if ('finanzas_page_aura-financial-categories' !== $hook) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'aura-financial-categories',
            AURA_PLUGIN_URL . 'assets/css/financial-categories.css',
            array(),
            AURA_VERSION
        );
        
        // Color Picker de WordPress
        wp_enqueue_style('wp-color-picker');
        
        // JavaScript
        wp_enqueue_script(
            'aura-financial-categories',
            AURA_PLUGIN_URL . 'assets/js/financial-categories.js',
            array('jquery', 'wp-color-picker'),
            AURA_VERSION,
            true
        );
        
        // Localizar script
        wp_localize_script('aura-financial-categories', 'auraCategories', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aura_categories_nonce'),
            'strings' => array(
                'confirmDelete' => __('¿Estás seguro de eliminar esta categoría? Esta acción no se puede deshacer.', 'aura-suite'),
                'confirmDeleteWithTransactions' => __('Esta categoría tiene %d transacción(es) asociada(s). No se puede eliminar. ¿Deseas desactivarla en su lugar?', 'aura-suite'),
                'categoryCreated' => __('Categoría creada exitosamente.', 'aura-suite'),
                'categoryUpdated' => __('Categoría actualizada exitosamente.', 'aura-suite'),
                'categoryDeleted' => __('Categoría eliminada exitosamente.', 'aura-suite'),
                'categoryDeactivated' => __('Categoría desactivada exitosamente.', 'aura-suite'),
                'categoryActivated' => __('Categoría activada exitosamente.', 'aura-suite'),
                'error' => __('Ocurrió un error. Por favor, intenta nuevamente.', 'aura-suite'),
                'nameRequired' => __('El nombre de la categoría es requerido.', 'aura-suite'),
                'invalidColor' => __('El color debe ser un código hexadecimal válido.', 'aura-suite'),
                'loading' => __('Cargando...', 'aura-suite'),
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
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'display_order';
        $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'ASC';
        
        // Construir condiciones WHERE
        $where = array('1=1');
        
        if (!empty($type) && in_array($type, array('income', 'expense', 'both'))) {
            $where[] = $wpdb->prepare("type = %s", $type);
        }
        
        if (!empty($status)) {
            $where[] = $wpdb->prepare("is_active = %d", $status === 'active' ? 1 : 0);
        }
        
        if (!empty($search)) {
            $where[] = $wpdb->prepare("(name LIKE %s OR description LIKE %s)", 
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
        $sql = "SELECT c.*, p.name as parent_name 
                FROM {$table} c 
                LEFT JOIN {$table} p ON c.parent_id = p.id 
                WHERE " . implode(' AND ', $where) . " 
                ORDER BY c.{$orderby} {$order}, c.name ASC";
        
        $results = $wpdb->get_results($sql);
        
        $categories = array();
        
        if ($results) {
            foreach ($results as $row) {
                $categories[] = array(
                    'id' => intval($row->id),
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'type' => $row->type,
                    'parent_id' => intval($row->parent_id),
                    'parent_name' => $row->parent_name ?: '',
                    'color' => $row->color ?: '#3498db',
                    'icon' => $row->icon ?: 'dashicons-category',
                    'description' => $row->description ?: '',
                    'is_active' => (bool) $row->is_active,
                    'display_order' => intval($row->display_order),
                    'transaction_count' => $this->get_transaction_count(intval($row->id)),
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
            'display_order' => intval($row->display_order),
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
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'both';
        $parent_id = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;
        $color = isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : '#3498db';
        $icon = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : 'dashicons-category';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $is_active = isset($_POST['is_active']) && $_POST['is_active'] === 'true' ? 1 : 0;
        $display_order = isset($_POST['display_order']) ? absint($_POST['display_order']) : 0;
        
        // Validaciones
        if (empty($name)) {
            wp_send_json_error(array('message' => __('El nombre de la categoría es requerido.', 'aura-suite')));
        }
        
        if (!in_array($type, array('income', 'expense', 'both'))) {
            wp_send_json_error(array('message' => __('Tipo de categoría inválido.', 'aura-suite')));
        }
        
        if (!$color) {
            $color = '#3498db';
        }
        
        // Generar slug único
        $slug = sanitize_title($name);
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
            'display_order' => $display_order,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );
        
        $result = $wpdb->insert($table, $data, array(
            '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s'
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
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'both';
        $parent_id = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;
        $color = isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : '#3498db';
        $icon = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : 'dashicons-category';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $is_active = isset($_POST['is_active']) && $_POST['is_active'] === 'true' ? 1 : 0;
        $display_order = isset($_POST['display_order']) ? absint($_POST['display_order']) : 0;
        
        // Validaciones
        if (empty($name)) {
            wp_send_json_error(array('message' => __('El nombre de la categoría es requerido.', 'aura-suite')));
        }
        
        if (!in_array($type, array('income', 'expense', 'both'))) {
            wp_send_json_error(array('message' => __('Tipo de categoría inválido.', 'aura-suite')));
        }
        
        if (!$color) {
            $color = '#3498db';
        }
        
        // Verificar jerarquía circular antes de actualizar
        if ($parent_id > 0 && $this->would_create_circular_hierarchy($category_id, $parent_id)) {
            wp_send_json_error(array('message' => __('La categoría padre seleccionada crearía una jerarquía circular.', 'aura-suite')));
        }
        
        // Generar nuevo slug si el nombre cambió
        $slug = $existing->slug;
        if ($name !== $existing->name) {
            $slug = sanitize_title($name);
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
            'display_order' => $display_order,
            'updated_at' => current_time('mysql'),
        );
        
        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $category_id),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s'),
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
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE category_id = %d 
             AND deleted_at IS NULL",
            $category_id
        ));
        
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
}

// Nota: La inicialización se realiza en aura-business-suite.php
// No inicializar automáticamente aquí para evitar registros duplicados
