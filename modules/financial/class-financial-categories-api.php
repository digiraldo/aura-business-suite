<?php
/**
 * API REST para Categorías Financieras
 *
 * Proporciona endpoints REST API para gestionar categorías financieras
 * desde frontend (VueJS/React) o aplicaciones externas.
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar la API REST de categorías financieras
 */
class Aura_Financial_Categories_API {
    
    /**
     * Namespace de la API
     */
    const API_NAMESPACE = 'aura/v1';
    
    /**
     * Base de la ruta
     */
    const API_BASE = 'finance/categories';
    
    /**
     * Inicializar la API
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }
    
    /**
     * Registrar rutas de la API
     */
    public static function register_routes() {
        // GET /wp-json/aura/v1/finance/categories
        register_rest_route(self::API_NAMESPACE, '/' . self::API_BASE, array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_categories'),
            'permission_callback' => array(__CLASS__, 'check_view_permission'),
            'args'                => array(
                'type' => array(
                    'description'       => __('Filtrar por tipo de categoría', 'aura-suite'),
                    'type'              => 'string',
                    'enum'              => array('income', 'expense', 'both'),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'status' => array(
                    'description'       => __('Filtrar por estado', 'aura-suite'),
                    'type'              => 'string',
                    'enum'              => array('active', 'inactive'),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'parent_id' => array(
                    'description'       => __('Filtrar por categoría padre', 'aura-suite'),
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'search' => array(
                    'description'       => __('Buscar por nombre', 'aura-suite'),
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // POST /wp-json/aura/v1/finance/categories
        register_rest_route(self::API_NAMESPACE, '/' . self::API_BASE, array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'create_category'),
            'permission_callback' => array(__CLASS__, 'check_manage_permission'),
            'args'                => array(
                'name' => array(
                    'description'       => __('Nombre de la categoría', 'aura-suite'),
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array(__CLASS__, 'validate_category_name'),
                ),
                'type' => array(
                    'description'       => __('Tipo de categoría', 'aura-suite'),
                    'type'              => 'string',
                    'required'          => true,
                    'enum'              => array('income', 'expense', 'both'),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'parent_id' => array(
                    'description'       => __('ID de categoría padre', 'aura-suite'),
                    'type'              => 'integer',
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ),
                'color' => array(
                    'description'       => __('Color en formato hexadecimal', 'aura-suite'),
                    'type'              => 'string',
                    'default'           => '#3498db',
                    'sanitize_callback' => 'sanitize_hex_color',
                ),
                'icon' => array(
                    'description'       => __('Clase del icono Dashicon', 'aura-suite'),
                    'type'              => 'string',
                    'default'           => 'dashicons-category',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'description' => array(
                    'description'       => __('Descripción de la categoría', 'aura-suite'),
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'status' => array(
                    'description'       => __('Estado de la categoría', 'aura-suite'),
                    'type'              => 'string',
                    'default'           => 'active',
                    'enum'              => array('active', 'inactive'),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'display_order' => array(
                    'description'       => __('Orden de visualización', 'aura-suite'),
                    'type'              => 'integer',
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // GET /wp-json/aura/v1/finance/categories/{id}
        register_rest_route(self::API_NAMESPACE, '/' . self::API_BASE . '/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_category'),
            'permission_callback' => array(__CLASS__, 'check_view_permission'),
            'args'                => array(
                'id' => array(
                    'description'       => __('ID de la categoría', 'aura-suite'),
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // PUT /wp-json/aura/v1/finance/categories/{id}
        register_rest_route(self::API_NAMESPACE, '/' . self::API_BASE . '/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array(__CLASS__, 'update_category'),
            'permission_callback' => array(__CLASS__, 'check_manage_permission'),
            'args'                => array(
                'id' => array(
                    'description'       => __('ID de la categoría', 'aura-suite'),
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ),
                'name' => array(
                    'description'       => __('Nombre de la categoría', 'aura-suite'),
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'type' => array(
                    'description'       => __('Tipo de categoría', 'aura-suite'),
                    'type'              => 'string',
                    'enum'              => array('income', 'expense', 'both'),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'parent_id' => array(
                    'description'       => __('ID de categoría padre', 'aura-suite'),
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'color' => array(
                    'description'       => __('Color en formato hexadecimal', 'aura-suite'),
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_hex_color',
                ),
                'icon' => array(
                    'description'       => __('Clase del icono Dashicon', 'aura-suite'),
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'description' => array(
                    'description'       => __('Descripción de la categoría', 'aura-suite'),
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'status' => array(
                    'description'       => __('Estado de la categoría', 'aura-suite'),
                    'type'              => 'string',
                    'enum'              => array('active', 'inactive'),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'display_order' => array(
                    'description'       => __('Orden de visualización', 'aura-suite'),
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // DELETE /wp-json/aura/v1/finance/categories/{id}
        register_rest_route(self::API_NAMESPACE, '/' . self::API_BASE . '/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array(__CLASS__, 'delete_category'),
            'permission_callback' => array(__CLASS__, 'check_manage_permission'),
            'args'                => array(
                'id' => array(
                    'description'       => __('ID de la categoría', 'aura-suite'),
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ),
                'force' => array(
                    'description'       => __('Eliminar permanentemente', 'aura-suite'),
                    'type'              => 'boolean',
                    'default'           => false,
                ),
            ),
        ));
        
        // GET /wp-json/aura/v1/finance/categories/tree
        register_rest_route(self::API_NAMESPACE, '/' . self::API_BASE . '/tree', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_categories_tree'),
            'permission_callback' => array(__CLASS__, 'check_view_permission'),
            'args'                => array(
                'type' => array(
                    'description'       => __('Filtrar por tipo de categoría', 'aura-suite'),
                    'type'              => 'string',
                    'enum'              => array('income', 'expense', 'both'),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }
    
    /**
     * Verificar permisos para ver categorías
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public static function check_view_permission($request) {
        return current_user_can('aura_finance_view_own') || 
               current_user_can('aura_finance_view_all') ||
               current_user_can('aura_finance_category_manage');
    }
    
    /**
     * Verificar permisos para gestionar categorías
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public static function check_manage_permission($request) {
        return current_user_can('aura_finance_category_manage');
    }
    
    /**
     * Validar nombre de categoría
     *
     * @param string $value
     * @param WP_REST_Request $request
     * @param string $param
     * @return bool|WP_Error
     */
    public static function validate_category_name($value, $request, $param) {
        if (empty($value)) {
            return new WP_Error(
                'empty_name',
                __('El nombre de la categoría no puede estar vacío.', 'aura-suite'),
                array('status' => 400)
            );
        }
        
        if (strlen($value) < 2) {
            return new WP_Error(
                'name_too_short',
                __('El nombre debe tener al menos 2 caracteres.', 'aura-suite'),
                array('status' => 400)
            );
        }
        
        return true;
    }
    
    /**
     * Obtener lista de categorías
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function get_categories($request) {
        $type = $request->get_param('type');
        $status = $request->get_param('status');
        $parent_id = $request->get_param('parent_id');
        $search = $request->get_param('search');
        
        $args = array(
            'post_type'      => 'aura_fin_category',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        );
        
        // Aplicar filtros
        $meta_query = array('relation' => 'AND');
        
        if (!empty($type)) {
            $meta_query[] = array(
                'key'     => '_aura_category_type',
                'value'   => $type,
                'compare' => '=',
            );
        }
        
        if (!empty($status)) {
            $meta_query[] = array(
                'key'     => '_aura_category_status',
                'value'   => $status,
                'compare' => '=',
            );
        }
        
        if (!empty($meta_query) && count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }
        
        if (isset($parent_id)) {
            $args['post_parent'] = $parent_id;
        }
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        $query = new WP_Query($args);
        $categories = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $categories[] = self::format_category_response($post_id);
            }
            wp_reset_postdata();
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $categories,
            'total'   => count($categories),
        ), 200);
    }
    
    /**
     * Obtener una categoría específica
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function get_category($request) {
        $category_id = $request->get_param('id');
        
        $post = get_post($category_id);
        
        if (!$post || $post->post_type !== 'aura_fin_category') {
            return new WP_Error(
                'category_not_found',
                __('Categoría no encontrada.', 'aura-suite'),
                array('status' => 404)
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data'    => self::format_category_response($category_id),
        ), 200);
    }
    
    /**
     * Crear nueva categoría
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function create_category($request) {
        $name = $request->get_param('name');
        $type = $request->get_param('type');
        $parent_id = $request->get_param('parent_id') ?: 0;
        $color = $request->get_param('color') ?: '#3498db';
        $icon = $request->get_param('icon') ?: 'dashicons-category';
        $description = $request->get_param('description') ?: '';
        $status = $request->get_param('status') ?: 'active';
        $display_order = $request->get_param('display_order') ?: 0;
        
        // Verificar slug único
        $slug = sanitize_title($name);
        if (get_page_by_path($slug, OBJECT, 'aura_fin_category')) {
            return new WP_Error(
                'duplicate_slug',
                __('Ya existe una categoría con ese nombre.', 'aura-suite'),
                array('status' => 400)
            );
        }
        
        // Verificar jerarquía circular
        if ($parent_id > 0) {
            if (self::would_create_circular_hierarchy($parent_id, 0)) {
                return new WP_Error(
                    'circular_hierarchy',
                    __('No se puede crear una jerarquía circular.', 'aura-suite'),
                    array('status' => 400)
                );
            }
        }
        
        // Crear post
        $post_data = array(
            'post_title'   => $name,
            'post_type'    => 'aura_fin_category',
            'post_status'  => 'publish',
            'post_parent'  => $parent_id,
            'menu_order'   => $display_order,
        );
        
        $category_id = wp_insert_post($post_data);
        
        if (is_wp_error($category_id)) {
            return new WP_Error(
                'creation_failed',
                __('Error al crear la categoría.', 'aura-suite'),
                array('status' => 500)
            );
        }
        
        // Guardar meta data
        update_post_meta($category_id, '_aura_category_type', $type);
        update_post_meta($category_id, '_aura_category_color', $color);
        update_post_meta($category_id, '_aura_category_icon', $icon);
        update_post_meta($category_id, '_aura_category_status', $status);
        
        if (!empty($description)) {
            update_post_meta($category_id, '_aura_category_description', $description);
        }
        
        do_action('aura_finance_category_created', $category_id, $request->get_params());
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Categoría creada exitosamente.', 'aura-suite'),
            'data'    => self::format_category_response($category_id),
        ), 201);
    }
    
    /**
     * Actualizar categoría existente
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function update_category($request) {
        $category_id = $request->get_param('id');
        
        $post = get_post($category_id);
        
        if (!$post || $post->post_type !== 'aura_fin_category') {
            return new WP_Error(
                'category_not_found',
                __('Categoría no encontrada.', 'aura-suite'),
                array('status' => 404)
            );
        }
        
        $post_data = array('ID' => $category_id);
        
        // Actualizar nombre si se proporciona
        if ($request->has_param('name')) {
            $name = $request->get_param('name');
            $post_data['post_title'] = $name;
        }
        
        // Actualizar padre si se proporciona
        if ($request->has_param('parent_id')) {
            $parent_id = $request->get_param('parent_id');
            
            // Verificar jerarquía circular
            if ($parent_id > 0 && self::would_create_circular_hierarchy($parent_id, $category_id)) {
                return new WP_Error(
                    'circular_hierarchy',
                    __('No se puede crear una jerarquía circular.', 'aura-suite'),
                    array('status' => 400)
                );
            }
            
            $post_data['post_parent'] = $parent_id;
        }
        
        // Actualizar orden si se proporciona
        if ($request->has_param('display_order')) {
            $post_data['menu_order'] = $request->get_param('display_order');
        }
        
        // Actualizar post
        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                'update_failed',
                __('Error al actualizar la categoría.', 'aura-suite'),
                array('status' => 500)
            );
        }
        
        // Actualizar meta data
        if ($request->has_param('type')) {
            update_post_meta($category_id, '_aura_category_type', $request->get_param('type'));
        }
        
        if ($request->has_param('color')) {
            update_post_meta($category_id, '_aura_category_color', $request->get_param('color'));
        }
        
        if ($request->has_param('icon')) {
            update_post_meta($category_id, '_aura_category_icon', $request->get_param('icon'));
        }
        
        if ($request->has_param('description')) {
            update_post_meta($category_id, '_aura_category_description', $request->get_param('description'));
        }
        
        if ($request->has_param('status')) {
            update_post_meta($category_id, '_aura_category_status', $request->get_param('status'));
        }
        
        do_action('aura_finance_category_updated', $category_id, $request->get_params());
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Categoría actualizada exitosamente.', 'aura-suite'),
            'data'    => self::format_category_response($category_id),
        ), 200);
    }
    
    /**
     * Eliminar categoría
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function delete_category($request) {
        global $wpdb;
        
        $category_id = $request->get_param('id');
        $force = $request->get_param('force');
        
        $post = get_post($category_id);
        
        if (!$post || $post->post_type !== 'aura_fin_category') {
            return new WP_Error(
                'category_not_found',
                __('Categoría no encontrada.', 'aura-suite'),
                array('status' => 404)
            );
        }
        
        // Verificar si tiene transacciones asociadas
        $transaction_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'aura_transaction' 
             AND ID IN (
                 SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_aura_transaction_category'
                 AND meta_value = %d
             )",
            $category_id
        ));
        
        if ($transaction_count > 0 && !$force) {
            return new WP_Error(
                'category_has_transactions',
                sprintf(
                    __('No se puede eliminar. Esta categoría tiene %d transacción(es) asociada(s).', 'aura-suite'),
                    $transaction_count
                ),
                array('status' => 400, 'transaction_count' => $transaction_count)
            );
        }
        
        // Verificar si tiene subcategorías
        $children = get_children(array(
            'post_parent' => $category_id,
            'post_type'   => 'aura_fin_category',
        ));
        
        if (!empty($children)) {
            return new WP_Error(
                'category_has_children',
                __('No se puede eliminar. Esta categoría tiene subcategorías.', 'aura-suite'),
                array('status' => 400)
            );
        }
        
        // Eliminar
        if ($force) {
            $result = wp_delete_post($category_id, true);
        } else {
            $result = wp_trash_post($category_id);
        }
        
        if (!$result) {
            return new WP_Error(
                'deletion_failed',
                __('Error al eliminar la categoría.', 'aura-suite'),
                array('status' => 500)
            );
        }
        
        do_action('aura_finance_category_deleted', $category_id, $force);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Categoría eliminada exitosamente.', 'aura-suite'),
        ), 200);
    }
    
    /**
     * Obtener árbol jerárquico de categorías
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_categories_tree($request) {
        $type = $request->get_param('type');
        
        $args = array(
            'post_type'      => 'aura_fin_category',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_status'    => 'publish',
            'post_parent'    => 0, // Solo padres
        );
        
        if (!empty($type)) {
            $args['meta_query'] = array(
                array(
                    'key'     => '_aura_category_type',
                    'value'   => $type,
                    'compare' => '=',
                ),
            );
        }
        
        $query = new WP_Query($args);
        $tree = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $category = self::format_category_response($post_id);
                $category['children'] = self::get_children_recursive($post_id, $type);
                
                $tree[] = $category;
            }
            wp_reset_postdata();
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $tree,
        ), 200);
    }
    
    /**
     * Obtener hijos recursivamente
     *
     * @param int $parent_id
     * @param string $type
     * @return array
     */
    private static function get_children_recursive($parent_id, $type = null) {
        $args = array(
            'post_type'      => 'aura_fin_category',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_status'    => 'publish',
            'post_parent'    => $parent_id,
        );
        
        if (!empty($type)) {
            $args['meta_query'] = array(
                array(
                    'key'     => '_aura_category_type',
                    'value'   => $type,
                    'compare' => '=',
                ),
            );
        }
        
        $query = new WP_Query($args);
        $children = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $child_id = get_the_ID();
                
                $child = self::format_category_response($child_id);
                $child['children'] = self::get_children_recursive($child_id, $type);
                
                $children[] = $child;
            }
            wp_reset_postdata();
        }
        
        return $children;
    }
    
    /**
     * Formatear respuesta de categoría
     *
     * @param int $post_id
     * @return array
     */
    private static function format_category_response($post_id) {
        global $wpdb;
        
        $post = get_post($post_id);
        
        // Contar transacciones
        $transaction_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'aura_transaction' 
             AND ID IN (
                 SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_aura_transaction_category'
                 AND meta_value = %d
             )",
            $post_id
        ));
        
        return array(
            'id'          => $post_id,
            'name'        => $post->post_title,
            'slug'        => $post->post_name,
            'type'        => get_post_meta($post_id, '_aura_category_type', true) ?: 'both',
            'parent_id'   => $post->post_parent,
            'parent_name' => $post->post_parent ? get_the_title($post->post_parent) : '',
            'color'       => get_post_meta($post_id, '_aura_category_color', true) ?: '#3498db',
            'icon'        => get_post_meta($post_id, '_aura_category_icon', true) ?: 'dashicons-category',
            'description' => get_post_meta($post_id, '_aura_category_description', true) ?: '',
            'status'      => get_post_meta($post_id, '_aura_category_status', true) ?: 'active',
            'order'       => $post->menu_order,
            'transactions' => (int) $transaction_count,
            'created_at'  => $post->post_date,
            'updated_at'  => $post->post_modified,
        );
    }
    
    /**
     * Verificar si se crearía una jerarquía circular
     *
     * @param int $parent_id
     * @param int $category_id
     * @param int $depth
     * @return bool
     */
    private static function would_create_circular_hierarchy($parent_id, $category_id, $depth = 0) {
        if ($depth > 10) {
            return true;
        }
        
        if ($parent_id == $category_id) {
            return true;
        }
        
        $parent = get_post($parent_id);
        
        if (!$parent || $parent->post_parent == 0) {
            return false;
        }
        
        return self::would_create_circular_hierarchy($parent->post_parent, $category_id, $depth + 1);
    }
}
