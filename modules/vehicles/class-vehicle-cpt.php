<?php
/**
 * Custom Post Type para Vehículos
 *
 * @package AuraBusinessSuite
 * @subpackage Vehicles
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar CPT de vehículos
 */
class Aura_Vehicle_CPT {
    
    /**
     * Inicializar el CPT
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_types'));
        add_action('init', array(__CLASS__, 'register_taxonomies'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_vehicle_meta'));
        add_action('admin_menu', array(__CLASS__, 'add_menu_pages'));
    }
    
    /**
     * Registrar Custom Post Types
     */
    public static function register_post_types() {
        // CPT: Vehículos
        register_post_type('aura_vehicle', array(
            'labels' => array(
                'name'          => __('Vehícullos', 'aura-suite'),
                'singular_name' => __('Vehículo', 'aura-suite'),
                'add_new_item'  => __('Agregar Nuevo Vehículo', 'aura-suite'),
            ),
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'aura-suite',
            'show_in_rest' => true,
            'supports'     => array('title', 'editor', 'thumbnail'),
            'menu_icon'    => 'dashicons-car',
            'capabilities' => array(
                'create_posts' => 'aura_vehicles_create',
                'edit_post'    => 'aura_vehicles_edit',
                'delete_post'  => 'aura_vehicles_delete',
                'read_post'    => 'aura_vehicles_view_all',
            ),
            'map_meta_cap' => true,
        ));
        
        // CPT: Salidas de Vehículos
        register_post_type('aura_vehicle_exit', array(
            'labels' => array(
                'name'          => __('Salidas de Vehículos', 'aura-suite'),
                'singular_name' => __('Salida', 'aura-suite'),
                'add_new_item'  => __('Registrar Nueva Salida', 'aura-suite'),
            ),
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'aura-suite',
            'show_in_rest' => true,
            'supports'     => array('title', 'editor', 'author'),
            'capabilities' => array(
                'create_posts' => 'aura_vehicles_exits_create',
                'edit_post'    => 'aura_vehicles_exits_edit_own',
                'read_post'    => 'aura_vehicles_view_all',
            ),
            'map_meta_cap' => true,
        ));
    }
    
    /**
     * Registrar taxonomías
     */
    public static function register_taxonomies() {
        register_taxonomy('aura_exit_type', 'aura_vehicle_exit', array(
            'labels' => array(
                'name'          => __('Tipos de Salida', 'aura-suite'),
                'singular_name' => __('Tipo', 'aura-suite'),
            ),
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
        ));
        
        // Crear términos por defecto
        $types = array(
            'maintenance' => __('Mantenimiento', 'aura-suite'),
            'repair'      => __('Reparación', 'aura-suite'),
            'rental'      => __('Renta', 'aura-suite'),
            'personal'    => __('Uso Personal', 'aura-suite'),
        );
        
        foreach ($types as $slug => $name) {
            if (!term_exists($slug, 'aura_exit_type')) {
                wp_insert_term($name, 'aura_exit_type', array('slug' => $slug));
            }
        }
    }
    
    /**
     * Agregar meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'aura_vehicle_details',
            __('Datos del Vehículo', 'aura-suite'),
            array(__CLASS__, 'render_vehicle_metabox'),
            'aura_vehicle',
            'normal',
            'high'
        );
        
        add_meta_box(
            'aura_exit_details',
            __('Datos de la Salida', 'aura-suite'),
            array(__CLASS__, 'render_exit_metabox'),
            'aura_vehicle_exit',
            'normal',
            'high'
        );
    }
    
    /**
     * Renderizar metabox de vehículo
     */
    public static function render_vehicle_metabox($post) {
        wp_nonce_field('aura_vehicle_meta', 'aura_vehicle_nonce');
        
        $plate = get_post_meta($post->ID, '_aura_vehicle_plate', true);
        $brand = get_post_meta($post->ID, '_aura_vehicle_brand', true);
        $model = get_post_meta($post->ID, '_aura_vehicle_model', true);
        $year = get_post_meta($post->ID, '_aura_vehicle_year', true);
        $current_km = get_post_meta($post->ID, '_aura_vehicle_current_km', true);
        $next_maintenance = get_post_meta($post->ID, '_aura_vehicle_next_maintenance_km', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="aura_plate"><?php _e('Placa', 'aura-suite'); ?></label></th>
                <td><input type="text" id="aura_plate" name="aura_plate" value="<?php echo esc_attr($plate); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="aura_brand"><?php _e('Marca', 'aura-suite'); ?></label></th>
                <td><input type="text" id="aura_brand" name="aura_brand" value="<?php echo esc_attr($brand); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="aura_model"><?php _e('Modelo', 'aura-suite'); ?></label></th>
                <td><input type="text" id="aura_model" name="aura_model" value="<?php echo esc_attr($model); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="aura_year"><?php _e('Año', 'aura-suite'); ?></label></th>
                <td><input type="number" id="aura_year" name="aura_year" value="<?php echo esc_attr($year); ?>" min="1900" max="<?php echo date('Y') + 1; ?>" required></td>
            </tr>
            <tr>
                <th><label for="aura_current_km"><?php _e('Kilometraje Actual', 'aura-suite'); ?></label></th>
                <td><input type="number" id="aura_current_km" name="aura_current_km" value="<?php echo esc_attr($current_km); ?>" min="0" required></td>
            </tr>
            <tr>
                <th><label for="aura_next_maintenance"><?php _e('Próximo Mantenimiento (km)', 'aura-suite'); ?></label></th>
                <td><input type="number" id="aura_next_maintenance" name="aura_next_maintenance" value="<?php echo esc_attr($next_maintenance); ?>" min="0"></td>
            </tr>
        </table>
        <?php
        
        // Mostrar alerta si está cerca del mantenimiento
        if ($current_km && $next_maintenance) {
            $km_remaining = $next_maintenance - $current_km;
            if ($km_remaining < 500) {
                echo '<div class="notice notice-warning"><p><strong>' . 
                     sprintf(__('⚠️ Mantenimiento próximo: Quedan %s km', 'aura-suite'), number_format($km_remaining)) . 
                     '</strong></p></div>';
            }
        }
    }
    
    /**
     * Renderizar metabox de salida
     */
    public static function render_exit_metabox($post) {
        wp_nonce_field('aura_exit_meta', 'aura_exit_nonce');
        
        $vehicle_id = get_post_meta($post->ID, '_aura_exit_vehicle_id', true);
        $exit_date = get_post_meta($post->ID, '_aura_exit_date', true);
        $return_date = get_post_meta($post->ID, '_aura_exit_return_date', true);
        $exit_km = get_post_meta($post->ID, '_aura_exit_km', true);
        $return_km = get_post_meta($post->ID, '_aura_exit_return_km', true);
        $driver = get_post_meta($post->ID, '_aura_exit_driver', true);
        
        // Obtener vehículos disponibles
        $vehicles = get_posts(array('post_type' => 'aura_vehicle', 'posts_per_page' => -1, 'post_status' => 'publish'));
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="aura_vehicle"><?php _e('Vehículo', 'aura-suite'); ?></label></th>
                <td>
                    <select id="aura_vehicle" name="aura_vehicle" required>
                        <option value=""><?php _e('Seleccionar...', 'aura-suite'); ?></option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?php echo $vehicle->ID; ?>" <?php selected($vehicle_id, $vehicle->ID); ?>>
                                <?php echo esc_html($vehicle->post_title); ?> - <?php echo esc_html(get_post_meta($vehicle->ID, '_aura_vehicle_plate', true)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="aura_exit_date"><?php _e('Fecha de Salida', 'aura-suite'); ?></label></th>
                <td><input type="datetime-local" id="aura_exit_date" name="aura_exit_date" value="<?php echo esc_attr($exit_date); ?>" required></td>
            </tr>
            <tr>
                <th><label for="aura_return_date"><?php _e('Fecha de Retorno', 'aura-suite'); ?></label></th>
                <td><input type="datetime-local" id="aura_return_date" name="aura_return_date" value="<?php echo esc_attr($return_date); ?>"></td>
            </tr>
            <tr>
                <th><label for="aura_exit_km"><?php _e('Kilometraje Salida', 'aura-suite'); ?></label></th>
                <td><input type="number" id="aura_exit_km" name="aura_exit_km" value="<?php echo esc_attr($exit_km); ?>" min="0" required></td>
            </tr>
            <tr>
                <th><label for="aura_return_km"><?php _e('Kilometraje Retorno', 'aura-suite'); ?></label></th>
                <td><input type="number" id="aura_return_km" name="aura_return_km" value="<?php echo esc_attr($return_km); ?>" min="0"></td>
            </tr>
            <tr>
                <th><label for="aura_driver"><?php _e('Conductor', 'aura-suite'); ?></label></th>
                <td><input type="text" id="aura_driver" name="aura_driver" value="<?php echo esc_attr($driver); ?>" class="regular-text" required></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Guardar metadata
     */
    public static function save_vehicle_meta($post_id) {
        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Guardar datos de vehículo
        if (isset($_POST['aura_vehicle_nonce']) && wp_verify_nonce($_POST['aura_vehicle_nonce'], 'aura_vehicle_meta')) {
            $fields = array('plate', 'brand', 'model', 'year', 'current_km', 'next_maintenance');
            foreach ($fields as $field) {
                if (isset($_POST['aura_' . $field])) {
                    update_post_meta($post_id, '_aura_vehicle_' . $field, sanitize_text_field($_POST['aura_' . $field]));
                }
            }
        }
        
        // Guardar datos de salida
        if (isset($_POST['aura_exit_nonce']) && wp_verify_nonce($_POST['aura_exit_nonce'], 'aura_exit_meta')) {
            $fields = array('vehicle' => 'vehicle_id', 'exit_date', 'return_date', 'exit_km', 'return_km', 'driver');
            foreach ($fields as $field_name => $meta_key) {
                $field = is_numeric($field_name) ? $meta_key : $field_name;
                $meta = is_numeric($field_name) ? $meta_key : $meta_key;
                
                if (isset($_POST['aura_' . $field])) {
                    update_post_meta($post_id, '_aura_exit_' . $meta, sanitize_text_field($_POST['aura_' . $field]));
                }
            }
            
            // Actualizar kilometraje del vehículo al registrar retorno
            if (isset($_POST['aura_return_km']) && $_POST['aura_return_km'] && isset($_POST['aura_vehicle'])) {
                $vehicle_id = intval($_POST['aura_vehicle']);
                $return_km = intval($_POST['aura_return_km']);
                update_post_meta($vehicle_id, '_aura_vehicle_current_km', $return_km);
            }
        }
    }
    
    /**
     * Agregar páginas de menú
     */
    public static function add_menu_pages() {
        // Módulo Vehículos pendiente de implementar como módulo independiente.
        // Las entradas de menú se habilitarán cuando se implemente el módulo completo.
    }
}
