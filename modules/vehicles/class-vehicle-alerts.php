<?php
/**
 * Sistema de Alertas de Vehículos
 *
 * @package AuraBusinessSuite
 * @subpackage Vehicles
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar alertas de mantenimiento
 */
class Aura_Vehicle_Alerts {
    
    /**
     * Inicializar sistema de alertas
     */
    public static function init() {
        // Programar cron job diario
        if (!wp_next_scheduled('aura_daily_vehicle_alerts')) {
            wp_schedule_event(time(), 'daily', 'aura_daily_vehicle_alerts');
        }
        
        add_action('aura_daily_vehicle_alerts', array(__CLASS__, 'check_and_send_alerts'));
    }
    
    /**
     * Verificar vehículos y enviar alertas
     */
    public static function check_and_send_alerts() {
        $vehicles = get_posts(array(
            'post_type'      => 'aura_vehicle',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ));
        
        foreach ($vehicles as $vehicle) {
            $current_km = intval(get_post_meta($vehicle->ID, '_aura_vehicle_current_km', true));
            $next_maintenance = intval(get_post_meta($vehicle->ID, '_aura_vehicle_next_maintenance_km', true));
            
            if ($current_km && $next_maintenance) {
                $km_remaining = $next_maintenance - $current_km;
                
                // Alertar si quedan menos de 500 km
                if ($km_remaining < 500 && $km_remaining >= 0) {
                    $alert_data = array(
                        'km_remaining' => $km_remaining,
                        'urgency'      => $km_remaining < 200 ? 'critical' : 'warning',
                    );
                    
                    Aura_Notifications::send_vehicle_maintenance_alert($vehicle->ID, $alert_data);
                }
            }
        }
    }
    
    /**
     * Obtener vehículos que requieren atención
     * 
     * @return array
     */
    public static function get_vehicles_needing_attention() {
        $vehicles = get_posts(array(
            'post_type'      => 'aura_vehicle',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ));
        
        $alerts = array();
        
        foreach ($vehicles as $vehicle) {
            $current_km = intval(get_post_meta($vehicle->ID, '_aura_vehicle_current_km', true));
            $next_maintenance = intval(get_post_meta($vehicle->ID, '_aura_vehicle_next_maintenance_km', true));
            
            if ($current_km && $next_maintenance) {
                $km_remaining = $next_maintenance - $current_km;
                
                if ($km_remaining < 500 && $km_remaining >= 0) {
                    $alerts[] = array(
                        'vehicle_id'   => $vehicle->ID,
                        'title'        => $vehicle->post_title,
                        'plate'        => get_post_meta($vehicle->ID, '_aura_vehicle_plate', true),
                        'current_km'   => $current_km,
                        'next_maintenance' => $next_maintenance,
                        'km_remaining' => $km_remaining,
                        'urgency'      => $km_remaining < 200 ? 'critical' : 'warning',
                    );
                }
            }
        }
        
        return $alerts;
    }
}
