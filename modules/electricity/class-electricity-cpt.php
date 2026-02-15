<?php
/**
 * Custom Post Type para Lecturas de Electricidad
 *
 * @package AuraBusinessSuite
 * @subpackage Electricity
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar CPT de lecturas eléctricas
 */
class Aura_Electricity_CPT {
    
    /**
     * Inicializar el CPT
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_reading_meta'));
        add_action('admin_menu', array(__CLASS__, 'add_menu_pages'));
        
        // Programar cron para alertas
        if (!wp_next_scheduled('aura_daily_electricity_alerts')) {
            wp_schedule_event(time(), 'daily', 'aura_daily_electricity_alerts');
        }
        add_action('aura_daily_electricity_alerts', array(__CLASS__, 'check_consumption_alerts'));
    }
    
    /**
     * Registrar Custom Post Type
     */
    public static function register_post_type() {
        register_post_type('aura_electric_reading', array(
            'labels' => array(
                'name'          => __('Lecturas Eléctricas', 'aura-suite'),
                'singular_name' => __('Lectura', 'aura-suite'),
                'add_new_item'  => __('Registrar Nueva Lectura', 'aura-suite'),
            ),
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'aura-suite',
            'show_in_rest' => true,
            'supports'     => array('title', 'editor', 'author'),
            'menu_icon'    => 'dashicons-lightbulb',
            'capabilities' => array(
                'create_posts' => 'aura_electric_reading_create',
                'edit_post'    => 'aura_electric_reading_edit_own',
                'delete_post'  => 'aura_electric_reading_delete',
                'read_post'    => 'aura_electric_view_dashboard',
            ),
            'map_meta_cap' => true,
        ));
    }
    
    /**
     * Agregar meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'aura_reading_details',
            __('Datos de la Lectura', 'aura-suite'),
            array(__CLASS__, 'render_reading_metabox'),
            'aura_electric_reading',
            'normal',
            'high'
        );
    }
    
    /**
     * Renderizar metabox
     */
    public static function render_reading_metabox($post) {
        wp_nonce_field('aura_reading_meta', 'aura_reading_nonce');
        
        $reading_date = get_post_meta($post->ID, '_aura_reading_date', true);
        $reading_kwh = get_post_meta($post->ID, '_aura_reading_kwh', true);
        $cost_per_kwh = get_post_meta($post->ID, '_aura_cost_per_kwh', true);
        
        // Calcular consumo diario
        $previous_reading = self::get_previous_reading($reading_date);
        $daily_consumption = 0;
        if ($previous_reading && $reading_kwh) {
            $prev_kwh = get_post_meta($previous_reading->ID, '_aura_reading_kwh', true);
            $daily_consumption = $reading_kwh - $prev_kwh;
        }
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="aura_reading_date"><?php _e('Fecha de Lectura', 'aura-suite'); ?></label></th>
                <td><input type="date" id="aura_reading_date" name="aura_reading_date" value="<?php echo esc_attr($reading_date ? $reading_date : date('Y-m-d')); ?>" required></td>
            </tr>
            <tr>
                <th><label for="aura_reading_kwh"><?php _e('Lectura (kWh)', 'aura-suite'); ?></label></th>
                <td><input type="number" id="aura_reading_kwh" name="aura_reading_kwh" value="<?php echo esc_attr($reading_kwh); ?>" step="0.01" min="0" required></td>
            </tr>
            <tr>
                <th><label for="aura_cost_per_kwh"><?php _e('Costo por kWh ($)', 'aura-suite'); ?></label></th>
                <td><input type="number" id="aura_cost_per_kwh" name="aura_cost_per_kwh" value="<?php echo esc_attr($cost_per_kwh ? $cost_per_kwh : '0.12'); ?>" step="0.01" min="0"></td>
            </tr>
            <?php if ($daily_consumption > 0): ?>
            <tr>
                <th><?php _e('Consumo Calculado', 'aura-suite'); ?></th>
                <td>
                    <strong><?php echo number_format($daily_consumption, 2); ?> kWh</strong>
                    <br><small><?php _e('(Desde la última lectura)', 'aura-suite'); ?></small>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }
    
    /**
     * Guardar metadata
     */
    public static function save_reading_meta($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (isset($_POST['aura_reading_nonce']) && wp_verify_nonce($_POST['aura_reading_nonce'], 'aura_reading_meta')) {
            if (isset($_POST['aura_reading_date'])) {
                update_post_meta($post_id, '_aura_reading_date', sanitize_text_field($_POST['aura_reading_date']));
            }
            if (isset($_POST['aura_reading_kwh'])) {
                $new_reading = floatval($_POST['aura_reading_kwh']);
                
                // Validar que la lectura sea mayor a la anterior
                $date = sanitize_text_field($_POST['aura_reading_date']);
                $previous = self::get_previous_reading($date);
                if ($previous) {
                    $prev_kwh = floatval(get_post_meta($previous->ID, '_aura_reading_kwh', true));
                    if ($new_reading < $prev_kwh) {
                        wp_die(__('Error: La lectura actual debe ser mayor o igual a la lectura anterior.', 'aura-suite'));
                    }
                }
                
                update_post_meta($post_id, '_aura_reading_kwh', $new_reading);
            }
            if (isset($_POST['aura_cost_per_kwh'])) {
                update_post_meta($post_id, '_aura_cost_per_kwh', sanitize_text_field($_POST['aura_cost_per_kwh']));
            }
        }
    }
    
    /**
     * Obtener lectura anterior
     */
    private static function get_previous_reading($date) {
        $args = array(
            'post_type'      => 'aura_electric_reading',
            'posts_per_page' => 1,
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'meta_key'       => '_aura_reading_date',
            'meta_query'     => array(
                array(
                    'key'     => '_aura_reading_date',
                    'value'   => $date,
                    'compare' => '<',
                    'type'    => 'DATE',
                ),
            ),
        );
        
        $readings = get_posts($args);
        return !empty($readings) ? $readings[0] : null;
    }
    
    /**
     * Verificar alertas de consumo
     */
    public static function check_consumption_alerts() {
        $threshold = floatval(get_option('aura_electric_threshold', 500));
        
        // Obtener última lectura
        $latest = get_posts(array(
            'post_type'      => 'aura_electric_reading',
            'posts_per_page' => 1,
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'meta_key'       => '_aura_reading_date',
        ));
        
        if (empty($latest)) {
            return;
        }
        
        $current_reading = $latest[0];
        $current_kwh = floatval(get_post_meta($current_reading->ID, '_aura_reading_kwh', true));
        $date = get_post_meta($current_reading->ID, '_aura_reading_date', true);
        
        $previous = self::get_previous_reading($date);
        if ($previous) {
            $prev_kwh = floatval(get_post_meta($previous->ID, '_aura_reading_kwh', true));
            $daily_consumption = $current_kwh - $prev_kwh;
            
            if ($daily_consumption > $threshold) {
                $percentage_over = (($daily_consumption - $threshold) / $threshold) * 100;
                
                Aura_Notifications::send_electricity_alert(array(
                    'current'         => $daily_consumption,
                    'threshold'       => $threshold,
                    'percentage_over' => $percentage_over,
                    'timestamp'       => strtotime($date),
                ));
            }
        }
    }
    
    /**
     * Agregar páginas de menú
     */
    public static function add_menu_pages() {
        if (current_user_can('aura_electric_view_dashboard')) {
            add_submenu_page(
                'aura-suite',
                __('Dashboard Electricidad', 'aura-suite'),
                __('Dashboard Electricidad', 'aura-suite'),
                'aura_electric_view_dashboard',
                'aura-electricity-dashboard',
                array('Aura_Electricity_Dashboard', 'render')
            );
            
            add_submenu_page(
                'aura-suite',
                __('Lecturas Eléctricas', 'aura-suite'),
                __('Lecturas', 'aura-suite'),
                'aura_electric_view_dashboard',
                'edit.php?post_type=aura_electric_reading'
            );
        }
    }
}
