<?php
/**
 * Custom Post Type para Categorías Financieras
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
 * Clase para gestionar CPT de categorías financieras
 */
class Aura_Financial_Categories_CPT {
    
    /**
     * Nombre del post type
     * 
     * @var string
     */
    const POST_TYPE = 'aura_fin_category';
    
    /**
     * Nombre de la tabla de categorías
     * 
     * @var string
     */
    const TABLE_NAME = 'aura_finance_categories';
    
    /**
     * Inicializar el CPT
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post_' . self::POST_TYPE, array(__CLASS__, 'save_category_meta'), 10, 2);
        add_action('before_delete_post', array(__CLASS__, 'prevent_delete_with_transactions'));
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array(__CLASS__, 'custom_columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array(__CLASS__, 'custom_column_content'), 10, 2);
        add_filter('wp_insert_post_data', array(__CLASS__, 'validate_unique_slug'), 10, 2);
        
        // Enqueue scripts y estilos en el admin
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
    }
    
    /**
     * Registrar Custom Post Type para Categorías Financieras
     */
    public static function register_post_type() {
        $labels = array(
            'name'                  => __('Categorías Financieras', 'aura-suite'),
            'singular_name'         => __('Categoría Financiera', 'aura-suite'),
            'add_new'               => __('Agregar Nueva', 'aura-suite'),
            'add_new_item'          => __('Agregar Nueva Categoría', 'aura-suite'),
            'edit_item'             => __('Editar Categoría', 'aura-suite'),
            'new_item'              => __('Nueva Categoría', 'aura-suite'),
            'view_item'             => __('Ver Categoría', 'aura-suite'),
            'search_items'          => __('Buscar Categorías', 'aura-suite'),
            'not_found'             => __('No se encontraron categorías', 'aura-suite'),
            'not_found_in_trash'    => __('No hay categorías en la papelera', 'aura-suite'),
            'all_items'             => __('Categorías Financieras', 'aura-suite'),
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false, // Ocultado - usar la interfaz AJAX en su lugar
            'show_in_rest'        => true,
            'capability_type'     => 'aura_finance_category',
            'map_meta_cap'        => true,
            'hierarchical'        => true, // Permite jerarquía padre-hijo
            'supports'            => array('title', 'custom-fields', 'page-attributes'),
            'has_archive'         => false,
            'rewrite'             => false,
            'menu_icon'           => 'dashicons-category',
            'menu_position'       => 25,
        );
        
        register_post_type(self::POST_TYPE, $args);
        
        // Agregar capabilities al rol administrator
        self::add_capabilities_to_admin();
    }
    
    /**
     * Agregar capabilities al rol de administrador
     */
    private static function add_capabilities_to_admin() {
        $admin = get_role('administrator');
        if ($admin) {
            $caps = array(
                'aura_finance_category_manage',
                'edit_aura_finance_category',
                'read_aura_finance_category',
                'delete_aura_finance_category',
                'edit_aura_finance_categorys',
                'edit_others_aura_finance_categorys',
                'publish_aura_finance_categorys',
                'read_private_aura_finance_categorys',
                'delete_aura_finance_categorys',
                'delete_private_aura_finance_categorys',
                'delete_published_aura_finance_categorys',
                'delete_others_aura_finance_categorys',
                'edit_private_aura_finance_categorys',
                'edit_published_aura_finance_categorys',
            );
            foreach ($caps as $cap) {
                if (!$admin->has_cap($cap)) {
                    $admin->add_cap($cap);
                }
            }
        }
    }
    
    /**
     * Agregar meta boxes personalizados
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'aura_category_details',
            __('Detalles de la Categoría', 'aura-suite'),
            array(__CLASS__, 'render_category_details_metabox'),
            self::POST_TYPE,
            'normal',
            'high'
        );
        
        add_meta_box(
            'aura_category_appearance',
            __('Apariencia', 'aura-suite'),
            array(__CLASS__, 'render_category_appearance_metabox'),
            self::POST_TYPE,
            'side',
            'default'
        );
        
        add_meta_box(
            'aura_category_status',
            __('Estado y Orden', 'aura-suite'),
            array(__CLASS__, 'render_category_status_metabox'),
            self::POST_TYPE,
            'side',
            'default'
        );
    }
    
    /**
     * Render meta box de detalles de categoría
     * 
     * @param WP_Post $post Post actual
     */
    public static function render_category_details_metabox($post) {
        // Nonce para seguridad
        wp_nonce_field('aura_category_meta', 'aura_category_meta_nonce');
        
        // Obtener valores actuales
        $category_type = get_post_meta($post->ID, '_aura_category_type', true) ?: 'both';
        $parent_id = $post->post_parent;
        $description = get_post_meta($post->ID, '_aura_category_description', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="aura_category_type"><?php _e('Tipo de Categoría', 'aura-suite'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="aura_category_type" value="income" <?php checked($category_type, 'income'); ?>>
                            <span class="dashicons dashicons-arrow-up-alt" style="color: #27ae60;"></span>
                            <?php _e('Ingresos', 'aura-suite'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="aura_category_type" value="expense" <?php checked($category_type, 'expense'); ?>>
                            <span class="dashicons dashicons-arrow-down-alt" style="color: #e74c3c;"></span>
                            <?php _e('Egresos', 'aura-suite'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="aura_category_type" value="both" <?php checked($category_type, 'both'); ?>>
                            <span class="dashicons dashicons-leftright"></span>
                            <?php _e('Ambos', 'aura-suite'); ?>
                        </label>
                    </fieldset>
                    <p class="description">
                        <?php _e('Selecciona si esta categoría se usará para ingresos, egresos o ambos.', 'aura-suite'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="parent_id"><?php _e('Categoría Padre', 'aura-suite'); ?></label>
                </th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'post_type'         => self::POST_TYPE,
                        'selected'          => $parent_id,
                        'name'              => 'parent_id',
                        'id'                => 'parent_id',
                        'show_option_none'  => __('Ninguna (Categoría principal)', 'aura-suite'),
                        'option_none_value' => '0',
                        'exclude'           => $post->ID, // No puede ser su propio padre
                    ));
                    ?>
                    <p class="description">
                        <?php _e('Selecciona una categoría padre para crear una jerarquía.', 'aura-suite'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="aura_category_description"><?php _e('Descripción', 'aura-suite'); ?></label>
                </th>
                <td>
                    <textarea name="aura_category_description" id="aura_category_description" rows="3" class="large-text"><?php echo esc_textarea($description); ?></textarea>
                    <p class="description">
                        <?php _e('Descripción opcional de esta categoría.', 'aura-suite'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render meta box de apariencia
     * 
     * @param WP_Post $post Post actual
     */
    public static function render_category_appearance_metabox($post) {
        $color = get_post_meta($post->ID, '_aura_category_color', true) ?: '#3498db';
        $icon = get_post_meta($post->ID, '_aura_category_icon', true) ?: 'dashicons-category';
        ?>
        <p>
            <label for="aura_category_color">
                <strong><?php _e('Color', 'aura-suite'); ?></strong>
            </label>
            <br>
            <input type="text" name="aura_category_color" id="aura_category_color" 
                   value="<?php echo esc_attr($color); ?>" 
                   class="aura-color-picker" 
                   data-default-color="#3498db">
        </p>
        
        <p>
            <label for="aura_category_icon">
                <strong><?php _e('Icono (Dashicon)', 'aura-suite'); ?></strong>
            </label>
            <br>
            <input type="text" name="aura_category_icon" id="aura_category_icon" 
                   value="<?php echo esc_attr($icon); ?>" 
                   placeholder="dashicons-category"
                   class="regular-text">
            <br>
            <span class="dashicons <?php echo esc_attr($icon); ?>" style="font-size: 32px; color: <?php echo esc_attr($color); ?>;"></span>
            <br>
            <small>
                <?php _e('Ej: dashicons-money-alt, dashicons-cart, dashicons-heart', 'aura-suite'); ?>
                <br>
                <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">
                    <?php _e('Ver todos los iconos disponibles', 'aura-suite'); ?>
                </a>
            </small>
        </p>
        <?php
    }
    
    /**
     * Render meta box de estado y orden
     * 
     * @param WP_Post $post Post actual
     */
    public static function render_category_status_metabox($post) {
        $is_active = get_post_meta($post->ID, '_aura_category_active', true);
        $is_active = ($is_active === '') ? '1' : $is_active; // Por defecto activa
        $display_order = get_post_meta($post->ID, '_aura_category_order', true) ?: '0';
        
        ?>
        <p>
            <label>
                <input type="checkbox" name="aura_category_active" value="1" <?php checked($is_active, '1'); ?>>
                <strong><?php _e('Activa', 'aura-suite'); ?></strong>
            </label>
            <br>
            <small><?php _e('Las categorías inactivas no aparecerán en los formularios.', 'aura-suite'); ?></small>
        </p>
        
        <p>
            <label for="aura_category_order">
                <strong><?php _e('Orden de visualización', 'aura-suite'); ?></strong>
            </label>
            <br>
            <input type="number" name="aura_category_order" id="aura_category_order" 
                   value="<?php echo esc_attr($display_order); ?>" 
                   min="0" 
                   step="1" 
                   class="small-text">
            <br>
            <small><?php _e('Menor número aparece primero.', 'aura-suite'); ?></small>
        </p>
        
        <?php
        // Mostrar cantidad de transacciones asociadas si existe
        if ($post->ID) {
            $transaction_count = self::get_transaction_count($post->ID);
            ?>
            <hr>
            <p>
                <strong><?php _e('Transacciones asociadas:', 'aura-suite'); ?></strong>
                <br>
                <span class="dashicons dashicons-money-alt"></span> 
                <?php echo esc_html($transaction_count); ?>
            </p>
            <?php if ($transaction_count > 0): ?>
                <p style="color: #856404; background: #fff3cd; padding: 8px; border-left: 3px solid #ffc107;">
                    <span class="dashicons dashicons-warning"></span>
                    <?php _e('No puedes eliminar una categoría con transacciones asociadas.', 'aura-suite'); ?>
                </p>
            <?php endif; ?>
        <?php
        }
    }
    
    /**
     * Guardar meta data de la categoría
     * 
     * @param int     $post_id ID del post
     * @param WP_Post $post    Post object
     */
    public static function save_category_meta($post_id, $post) {
        // Verificar nonce
        if (!isset($_POST['aura_category_meta_nonce']) || 
            !wp_verify_nonce($_POST['aura_category_meta_nonce'], 'aura_category_meta')) {
            return;
        }
        
        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Guardar tipo de categoría
        if (isset($_POST['aura_category_type'])) {
            $category_type = sanitize_text_field($_POST['aura_category_type']);
            if (in_array($category_type, array('income', 'expense', 'both'))) {
                update_post_meta($post_id, '_aura_category_type', $category_type);
            }
        }
        
        // Guardar descripción
        if (isset($_POST['aura_category_description'])) {
            update_post_meta($post_id, '_aura_category_description', 
                sanitize_textarea_field($_POST['aura_category_description']));
        }
        
        // Guardar color
        if (isset($_POST['aura_category_color'])) {
            $color = sanitize_hex_color($_POST['aura_category_color']);
            if ($color) {
                update_post_meta($post_id, '_aura_category_color', $color);
            }
        }
        
        // Guardar icono
        if (isset($_POST['aura_category_icon'])) {
            update_post_meta($post_id, '_aura_category_icon', 
                sanitize_text_field($_POST['aura_category_icon']));
        }
        
        // Guardar estado activo
        $is_active = isset($_POST['aura_category_active']) ? '1' : '0';
        update_post_meta($post_id, '_aura_category_active', $is_active);
        
        // Guardar orden
        if (isset($_POST['aura_category_order'])) {
            update_post_meta($post_id, '_aura_category_order', 
                absint($_POST['aura_category_order']));
        }
        
        // Validar jerarquía para prevenir ciclos
        if (isset($_POST['parent_id'])) {
            $parent_id = absint($_POST['parent_id']);
            if ($parent_id > 0 && !self::would_create_circular_hierarchy($post_id, $parent_id)) {
                wp_update_post(array(
                    'ID'          => $post_id,
                    'post_parent' => $parent_id,
                ));
            } else if ($parent_id > 0) {
                // Si crearía un ciclo, mostrar error en la próxima carga
                add_settings_error(
                    'aura_category_messages',
                    'circular_hierarchy',
                    __('No se puede establecer esta categoría como padre porque crearía una jerarquía circular.', 'aura-suite'),
                    'error'
                );
            }
        }
    }
    
    /**
     * Validar que el slug sea único
     * 
     * @param array $data    Array de datos del post
     * @param array $postarr Array de datos enviados
     * @return array
     */
    public static function validate_unique_slug($data, $postarr) {
        if ($data['post_type'] !== self::POST_TYPE) {
            return $data;
        }
        
        // Verificar slug único
        $slug = $data['post_name'];
        $post_id = isset($postarr['ID']) ? $postarr['ID'] : 0;
        
        if (!empty($slug)) {
            global $wpdb;
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE post_name = %s 
                 AND post_type = %s 
                 AND ID != %d 
                 AND post_status != 'trash'",
                $slug,
                self::POST_TYPE,
                $post_id
            ));
            
            if ($existing) {
                // Agregar sufijo numérico si existe
                $suffix = 2;
                $new_slug = $slug;
                while ($wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} 
                     WHERE post_name = %s 
                     AND post_type = %s 
                     AND post_status != 'trash'",
                    $new_slug,
                    self::POST_TYPE
                ))) {
                    $new_slug = $slug . '-' . $suffix;
                    $suffix++;
                }
                $data['post_name'] = $new_slug;
            }
        }
        
        return $data;
    }
    
    /**
     * Prevenir eliminación de categorías con transacciones
     * 
     * @param int $post_id ID del post a eliminar
     */
    public static function prevent_delete_with_transactions($post_id) {
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }
        
        $transaction_count = self::get_transaction_count($post_id);
        
        if ($transaction_count > 0) {
            wp_die(
                sprintf(
                    __('No puedes eliminar esta categoría porque tiene %d transaccion(es) asociada(s). Por favor, reasigna o elimina las transacciones primero, o desactiva la categoría.', 'aura-suite'),
                    $transaction_count
                ),
                __('Error al eliminar', 'aura-suite'),
                array('back_link' => true)
            );
        }
    }
    
    /**
     * Obtener cantidad de transacciones asociadas a una categoría
     * 
     * @param int $category_id ID de la categoría
     * @return int
     */
    private static function get_transaction_count($category_id) {
        global $wpdb;
        
        // Verificar si la tabla de transacciones existe
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
     * Verificar si establecer un padre crearía una jerarquía circular
     * 
     * @param int $post_id   ID del post
     * @param int $parent_id ID del padre propuesto
     * @return bool True si crearía un ciclo
     */
    private static function would_create_circular_hierarchy($post_id, $parent_id) {
        if ($parent_id == 0 || $parent_id == $post_id) {
            return false;
        }
        
        // Recorrer hacia arriba en la jerarquía
        $current_parent = $parent_id;
        $max_depth = 10; // Prevenir loops infinitos
        $depth = 0;
        
        while ($current_parent > 0 && $depth < $max_depth) {
            if ($current_parent == $post_id) {
                return true; // Ciclo detectado
            }
            
            $parent_post = get_post($current_parent);
            if (!$parent_post) {
                break;
            }
            
            $current_parent = $parent_post->post_parent;
            $depth++;
        }
        
        return false;
    }
    
    /**
     * Definir columnas personalizadas en el listado
     * 
     * @param array $columns Columnas existentes
     * @return array
     */
    public static function custom_columns($columns) {
        $new_columns = array(
            'cb'          => $columns['cb'],
            'title'       => __('Nombre', 'aura-suite'),
            'type'        => __('Tipo', 'aura-suite'),
            'parent'      => __('Categoría Padre', 'aura-suite'),
            'color'       => __('Color', 'aura-suite'),
            'icon'        => __('Icono', 'aura-suite'),
            'status'      => __('Estado', 'aura-suite'),
            'order'       => __('Orden', 'aura-suite'),
            'count'       => __('Transacciones', 'aura-suite'),
            'date'        => $columns['date'],
        );
        
        return $new_columns;
    }
    
    /**
     * Contenido de las columnas personalizadas
     * 
     * @param string $column  Nombre de la columna
     * @param int    $post_id ID del post
     */
    public static function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'type':
                $type = get_post_meta($post_id, '_aura_category_type', true);
                $icons = array(
                    'income'  => '<span class="dashicons dashicons-arrow-up-alt" style="color: #27ae60;" title="' . __('Ingresos', 'aura-suite') . '"></span>',
                    'expense' => '<span class="dashicons dashicons-arrow-down-alt" style="color: #e74c3c;" title="' . __('Egresos', 'aura-suite') . '"></span>',
                    'both'    => '<span class="dashicons dashicons-leftright" style="color: #3498db;" title="' . __('Ambos', 'aura-suite') . '"></span>',
                );
                echo isset($icons[$type]) ? $icons[$type] : '—';
                break;
                
            case 'parent':
                $post = get_post($post_id);
                if ($post->post_parent > 0) {
                    $parent = get_post($post->post_parent);
                    if ($parent) {
                        echo '<a href="' . get_edit_post_link($parent->ID) . '">' . esc_html($parent->post_title) . '</a>';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'color':
                $color = get_post_meta($post_id, '_aura_category_color', true) ?: '#3498db';
                echo '<span style="display: inline-block; width: 20px; height: 20px; background-color: ' . esc_attr($color) . '; border: 1px solid #ccc; border-radius: 3px;"></span>';
                break;
                
            case 'icon':
                $icon = get_post_meta($post_id, '_aura_category_icon', true) ?: 'dashicons-category';
                $color = get_post_meta($post_id, '_aura_category_color', true) ?: '#3498db';
                echo '<span class="dashicons ' . esc_attr($icon) . '" style="font-size: 20px; color: ' . esc_attr($color) . ';"></span>';
                break;
                
            case 'status':
                $is_active = get_post_meta($post_id, '_aura_category_active', true);
                if ($is_active === '1' || $is_active === '') {
                    echo '<span class="dashicons dashicons-yes-alt" style="color: #27ae60;" title="' . __('Activa', 'aura-suite') . '"></span>';
                } else {
                    echo '<span class="dashicons dashicons-dismiss" style="color: #e74c3c;" title="' . __('Inactiva', 'aura-suite') . '"></span>';
                }
                break;
                
            case 'order':
                $order = get_post_meta($post_id, '_aura_category_order', true);
                echo $order !== '' ? esc_html($order) : '0';
                break;
                
            case 'count':
                $count = self::get_transaction_count($post_id);
                if ($count > 0) {
                    echo '<span class="dashicons dashicons-money-alt"></span> ' . esc_html($count);
                } else {
                    echo '—';
                }
                break;
        }
    }
    
    /**
     * Enqueue scripts y estilos para el admin
     * 
     * @param string $hook Hook del admin actual
     */
    public static function enqueue_admin_assets($hook) {
        // Solo cargar en páginas de edición del CPT
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }
        
        // Color Picker de WordPress
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Script personalizado
        wp_enqueue_script(
            'aura-category-admin',
            AURA_PLUGIN_URL . 'assets/js/admin-scripts.js',
            array('jquery', 'wp-color-picker'),
            AURA_VERSION,
            true
        );
        
        // Agregar inline script para inicializar color picker
        wp_add_inline_script('aura-category-admin', '
            jQuery(document).ready(function($) {
                $(".aura-color-picker").wpColorPicker();
                
                // Preview del icono en tiempo real
                $("#aura_category_icon").on("input", function() {
                    var iconClass = $(this).val();
                    var color = $("#aura_category_color").val();
                    $(this).next("br").next(".dashicons")
                        .attr("class", "dashicons " + iconClass)
                        .css("color", color);
                });
                
                // Actualizar color del icono en tiempo real
                $("#aura_category_color").wpColorPicker({
                    change: function(event, ui) {
                        var color = ui.color.toString();
                        $("#aura_category_icon").next("br").next(".dashicons").css("color", color);
                    }
                });
            });
        ');
    }
    
    /**
     * Crear tabla de categorías en la base de datos
     * Hook de activación del plugin
     */
    public static function create_categories_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            type ENUM('income', 'expense', 'both') DEFAULT 'both',
            parent_id BIGINT UNSIGNED NULL,
            color VARCHAR(7) DEFAULT '#3498db',
            icon VARCHAR(50) DEFAULT 'dashicons-category',
            description TEXT,
            is_active BOOLEAN DEFAULT 1,
            display_order INT DEFAULT 0,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES $table_name(id) ON DELETE SET NULL,
            INDEX idx_type (type),
            INDEX idx_active (is_active),
            INDEX idx_parent (parent_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Registrar versión de la tabla
        add_option('aura_finance_categories_db_version', '1.0');
    }
    
    /**
     * Sincronizar categorías CPT con tabla personalizada
     * Útil para mantener ambos sistemas sincronizados
     * 
     * @param int $post_id ID del post de categoría
     */
    public static function sync_category_to_table($post_id) {
        global $wpdb;
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return;
        }
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        $data = array(
            'name'          => $post->post_title,
            'slug'          => $post->post_name,
            'type'          => get_post_meta($post_id, '_aura_category_type', true) ?: 'both',
            'parent_id'     => $post->post_parent > 0 ? $post->post_parent : null,
            'color'         => get_post_meta($post_id, '_aura_category_color', true) ?: '#3498db',
            'icon'          => get_post_meta($post_id, '_aura_category_icon', true) ?: 'dashicons-category',
            'description'   => get_post_meta($post_id, '_aura_category_description', true),
            'is_active'     => get_post_meta($post_id, '_aura_category_active', true) === '1' ? 1 : 0,
            'display_order' => get_post_meta($post_id, '_aura_category_order', true) ?: 0,
            'created_by'    => $post->post_author,
        );
        
        // Verificar si ya existe en la tabla
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE id = %d",
            $post_id
        ));
        
        if ($exists) {
            $wpdb->update($table_name, $data, array('id' => $post_id));
        } else {
            $data['id'] = $post_id;
            $wpdb->insert($table_name, $data);
        }
    }
}

// Nota: La inicialización se realiza en aura-business-suite.php
// No inicializar automáticamente aquí para evitar registros duplicados
