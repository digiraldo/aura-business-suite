<?php
/**
 * Reportes de Vehículos
 *
 * @package AuraBusinessSuite
 * @subpackage Vehicles
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar reportes de vehículos
 */
class Aura_Vehicle_Reports {
    
    /**
     * Renderizar página de reportes
     */
    public static function render() {
        if (!current_user_can('aura_vehicles_reports')) {
            wp_die(__('No tienes permiso para acceder a esta página.', 'aura-suite'));
        }
        
        ?>
        <div class="wrap aura-vehicle-reports">
            <h1><?php _e('Reportes de Vehículos', 'aura-suite'); ?></h1>
            
            <div class="aura-report-section">
                <h2><?php _e('Alertas de Mantenimiento', 'aura-suite'); ?></h2>
                <?php self::render_maintenance_alerts(); ?>
            </div>
            
            <div class="aura-report-section">
                <h2><?php _e('Kilometraje por Vehículo', 'aura-suite'); ?></h2>
                <?php self::render_mileage_report(); ?>
            </div>
            
            <div class="aura-chart-container">
                <h2><?php _e('Salidas por Tipo', 'aura-suite'); ?></h2>
                <canvas id="aura-exits-by-type-chart"></canvas>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizar alertas de mantenimiento
     */
    private static function render_maintenance_alerts() {
        $alerts = Aura_Vehicle_Alerts::get_vehicles_needing_attention();
        
        if (empty($alerts)) {
            echo '<p>' . __('No hay vehículos que requieran atención inmediata.', 'aura-suite') . '</p>';
            return;
        }
        
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Vehículo', 'aura-suite'); ?></th>
                    <th><?php _e('Placa', 'aura-suite'); ?></th>
                    <th><?php _e('Kilometraje Actual', 'aura-suite'); ?></th>
                    <th><?php _e('Próximo Mantenimiento', 'aura-suite'); ?></th>
                    <th><?php _e('Km Restantes', 'aura-suite'); ?></th>
                    <th><?php _e('Urgencia', 'aura-suite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alerts as $alert): ?>
                <tr class="alert-row alert-<?php echo esc_attr($alert['urgency']); ?>">
                    <td><?php echo esc_html($alert['title']); ?></td>
                    <td><?php echo esc_html($alert['plate']); ?></td>
                    <td><?php echo number_format($alert['current_km']); ?> km</td>
                    <td><?php echo number_format($alert['next_maintenance']); ?> km</td>
                    <td><strong><?php echo number_format($alert['km_remaining']); ?> km</strong></td>
                    <td>
                        <span class="urgency-badge urgency-<?php echo esc_attr($alert['urgency']); ?>">
                            <?php echo $alert['urgency'] === 'critical' ? __('CRÍTICO', 'aura-suite') : __('Advertencia', 'aura-suite'); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <style>
            .alert-row.alert-critical { background-color: #fee2e2 !important; }
            .alert-row.alert-warning { background-color: #fef3c7 !important; }
            .urgency-badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; display: inline-block; }
            .urgency-critical { background: #dc2626; color: white; }
            .urgency-warning { background: #f59e0b; color: white; }
        </style>
        <?php
    }
    
    /**
     * Renderizar reporte de kilometraje
     */
    private static function render_mileage_report() {
        $vehicles = get_posts(array(
            'post_type'      => 'aura_vehicle',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ));
        
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Vehículo', 'aura-suite'); ?></th>
                    <th><?php _e('Placa', 'aura-suite'); ?></th>
                    <th><?php _e('Marca/Modelo', 'aura-suite'); ?></th>
                    <th><?php _e('Kilometraje Actual', 'aura-suite'); ?></th>
                    <th><?php _e('Total Salidas', 'aura-suite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vehicles as $vehicle): 
                    $plate = get_post_meta($vehicle->ID, '_aura_vehicle_plate', true);
                    $brand = get_post_meta($vehicle->ID, '_aura_vehicle_brand', true);
                    $model = get_post_meta($vehicle->ID, '_aura_vehicle_model', true);
                    $current_km = get_post_meta($vehicle->ID, '_aura_vehicle_current_km', true);
                    
                    $exits_count = count(get_posts(array(
                        'post_type'  => 'aura_vehicle_exit',
                        'meta_query' => array(
                            array(
                                'key'   => '_aura_exit_vehicle_id',
                                'value' => $vehicle->ID,
                            ),
                        ),
                        'posts_per_page' => -1,
                    )));
                ?>
                <tr>
                    <td><?php echo esc_html($vehicle->post_title); ?></td>
                    <td><?php echo esc_html($plate); ?></td>
                    <td><?php echo esc_html($brand . ' ' . $model); ?></td>
                    <td><?php echo number_format($current_km); ?> km</td>
                    <td><?php echo $exits_count; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
