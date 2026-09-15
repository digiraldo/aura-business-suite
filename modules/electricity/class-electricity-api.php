<?php
/**
 * REST API para Electricidad (IoT)
 *
 * @package AuraBusinessSuite
 * @subpackage Electricity
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar API REST de electricidad
 */
class Aura_Electricity_API {
    
    /**
     * Inicializar API
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }
    
    /**
     * Registrar rutas de la API
     */
    public static function register_routes() {
        register_rest_route('aura/v1', '/electricity/reading', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'create_reading'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
            'args'                => array(
                'reading_kwh' => array(
                    'required'          => true,
                    'type'              => 'number',
                    'description'       => __('Lectura en kWh', 'aura-suite'),
                    'validate_callback' => function($value) {
                        return is_numeric($value) && $value >= 0;
                    },
                ),
                'cost_per_kwh' => array(
                    'required'          => false,
                    'type'              => 'number',
                    'default'           => 0.12,
                    'description'       => __('Costo por kWh', 'aura-suite'),
                ),
                'api_key' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
            ),
        ));
        
        register_rest_route('aura/v1', '/electricity/consumption', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_consumption_data'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
            'args'                => array(
                'days' => array(
                    'default' => 30,
                    'type'    => 'integer',
                ),
                'api_key' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
            ),
        ));
    }
    
    /**
     * Verificar permisos de API
     */
    public static function check_permission($request) {
        $api_key = $request->get_param('api_key');
        $stored_key = get_option('aura_electricity_api_key', '');
        
        // Si no hay API key configurada, generarla
        if (empty($stored_key)) {
            $stored_key = wp_generate_password(32, false);
            update_option('aura_electricity_api_key', $stored_key);
        }
        
        return $api_key === $stored_key;
    }
    
    /**
     * Crear lectura via API
     */
    public static function create_reading($request) {
        $reading_kwh = $request->get_param('reading_kwh');
        $cost_per_kwh = $request->get_param('cost_per_kwh');
        
        $post_id = wp_insert_post(array(
            'post_type'   => 'aura_electric_reading',
            'post_title'  => sprintf(__('Lectura automática %s', 'aura-suite'), date_i18n(get_option('date_format'))),
            'post_status' => 'publish',
            'post_author' => 1,
        ));
        
        if (is_wp_error($post_id)) {
            return new WP_Error('create_failed', __('No se pudo crear la lectura', 'aura-suite'), array('status' => 500));
        }
        
        update_post_meta($post_id, '_aura_reading_date', date('Y-m-d'));
        update_post_meta($post_id, '_aura_reading_kwh', $reading_kwh);
        update_post_meta($post_id, '_aura_cost_per_kwh', $cost_per_kwh);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Lectura registrada exitosamente', 'aura-suite'),
            'post_id' => $post_id,
            'data'    => array(
                'reading_kwh'  => $reading_kwh,
                'cost_per_kwh' => $cost_per_kwh,
                'date'         => date('Y-m-d'),
            ),
        ));
    }
    
    /**
     * Obtener datos de consumo
     */
    public static function get_consumption_data($request) {
        $days = $request->get_param('days');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $readings = get_posts(array(
            'post_type'      => 'aura_electric_reading',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_key'       => '_aura_reading_date',
            'meta_query'     => array(
                array(
                    'key'     => '_aura_reading_date',
                    'value'   => $start_date,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            ),
        ));
        
        $data = array();
        foreach ($readings as $reading) {
            $data[] = array(
                'date'        => get_post_meta($reading->ID, '_aura_reading_date', true),
                'reading_kwh' => floatval(get_post_meta($reading->ID, '_aura_reading_kwh', true)),
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data'    => $data,
            'count'   => count($data),
        ));
    }
}
