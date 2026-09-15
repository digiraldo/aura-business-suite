<?php
/**
 * Dashboard de Electricidad
 *
 * @package AuraBusinessSuite
 * @subpackage Electricity
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar dashboard de electricidad
 */
class Aura_Electricity_Dashboard {
    
    /**
     * Renderizar dashboard
     */
    public static function render() {
        if (!current_user_can('aura_electric_view_dashboard')) {
            wp_die(__('No tienes permiso para acceder a esta página.', 'aura-suite'));
        }
        
        $stats = self::get_consumption_stats();
        
        ?>
        <div class="wrap aura-electricity-dashboard">
            <h1><?php _e('Dashboard de Consumo Eléctrico', 'aura-suite'); ?></h1>
            
            <div class="aura-kpis-grid">
                <div class="aura-kpi-card">
                    <div class="kpi-icon">⚡</div>
                    <div class="kpi-content">
                        <h3><?php _e('Consumo Promedio Diario', 'aura-suite'); ?></h3>
                        <p class="kpi-value"><?php echo number_format($stats['avg_daily'], 2); ?> kWh</p>
                    </div>
                </div>
                
                <div class="aura-kpi-card">
                    <div class="kpi-icon">📊</div>
                    <div class="kpi-content">
                        <h3><?php _e('Consumo del Mes', 'aura-suite'); ?></h3>
                        <p class="kpi-value"><?php echo number_format($stats['monthly'], 2); ?> kWh</p>
                    </div>
                </div>
                
                <div class="aura-kpi-card">
                    <div class="kpi-icon">💰</div>
                    <div class="kpi-content">
                        <h3><?php _e('Costo Proyectado', 'aura-suite'); ?></h3>
                        <p class="kpi-value">$<?php echo number_format($stats['projected_cost'], 2); ?></p>
                    </div>
                </div>
                
                <div class="aura-kpi-card">
                    <div class="kpi-icon">🔥</div>
                    <div class="kpi-content">
                        <h3><?php _e('Consumo Pico', 'aura-suite'); ?></h3>
                        <p class="kpi-value"><?php echo number_format($stats['peak'], 2); ?> kWh</p>
                    </div>
                </div>
            </div>
            
            <div class="aura-chart-container">
                <h2><?php _e('Consumo Diario (Últimos 30 días)', 'aura-suite'); ?></h2>
                <canvas id="aura-electricity-chart"></canvas>
            </div>
            
            <?php if (current_user_can('aura_electric_thresholds_config')): ?>
            <div class="aura-config-section">
                <h2><?php _e('Configuración de Alertas', 'aura-suite'); ?></h2>
                <?php self::render_threshold_config(); ?>
            </div>
            <?php endif; ?>
            
            <?php if (current_user_can('aura_electric_view_charts')): ?>
            <div class="aura-chart-container">
                <h2><?php _e('Comparativa Mensual', 'aura-suite'); ?></h2>
                <canvas id="aura-electricity-comparison-chart"></canvas>
            </div>
            <?php endif; ?>
        </div>
        <?php
        
        // Cargar datos para gráficos
        self::enqueue_chart_data();
    }
    
    /**
     * Obtener estadísticas de consumo
     */
    private static function get_consumption_stats() {
        $current_month_start = date('Y-m-01');
        $last_30_days = date('Y-m-d', strtotime('-30 days'));
        
        $readings = get_posts(array(
            'post_type'      => 'aura_electric_reading',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_key'       => '_aura_reading_date',
            'meta_query'     => array(
                array(
                    'key'     => '_aura_reading_date',
                    'value'   => $last_30_days,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            ),
        ));
        
        $monthly_consumption = 0;
        $daily_consumptions = array();
        $total_cost = 0;
        
        for ($i = 1; $i < count($readings); $i++) {
            $current_kwh = floatval(get_post_meta($readings[$i]->ID, '_aura_reading_kwh', true));
            $previous_kwh = floatval(get_post_meta($readings[$i - 1]->ID, '_aura_reading_kwh', true));
            $cost_per_kwh = floatval(get_post_meta($readings[$i]->ID, '_aura_cost_per_kwh', true));
            $date = get_post_meta($readings[$i]->ID, '_aura_reading_date', true);
            
            $daily = $current_kwh - $previous_kwh;
            $daily_consumptions[] = $daily;
            
            if ($date >= $current_month_start) {
                $monthly_consumption += $daily;
                $total_cost += $daily * $cost_per_kwh;
            }
        }
        
        $avg_daily = !empty($daily_consumptions) ? array_sum($daily_consumptions) / count($daily_consumptions) : 0;
        $peak = !empty($daily_consumptions) ? max($daily_consumptions) : 0;
        
        // Proyectar costo del mes
        $days_in_month = date('t');
        $days_elapsed = date('j');
        $projected_cost = ($days_in_month / $days_elapsed) * $total_cost;
        
        return array(
            'avg_daily'       => $avg_daily,
            'monthly'         => $monthly_consumption,
            'projected_cost'  => $projected_cost,
            'peak'            => $peak,
        );
    }
    
    /**
     * Renderizar configuración de umbrales
     */
    private static function render_threshold_config() {
        if (isset($_POST['aura_save_threshold']) && wp_verify_nonce($_POST['aura_threshold_nonce'], 'save_threshold')) {
            $threshold = floatval($_POST['threshold']);
            update_option('aura_electric_threshold', $threshold);
            echo '<div class="notice notice-success"><p>' . __('Umbral guardado exitosamente.', 'aura-suite') . '</p></div>';
        }
        
        $threshold = get_option('aura_electric_threshold', 500);
        
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('save_threshold', 'aura_threshold_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="threshold"><?php _e('Umbral de Alerta (kWh/día)', 'aura-suite'); ?></label></th>
                    <td>
                        <input type="number" id="threshold" name="threshold" value="<?php echo esc_attr($threshold); ?>" step="0.01" min="0" class="regular-text">
                        <p class="description"><?php _e('Se enviará una alerta cuando el consumo diario supere este valor.', 'aura-suite'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="aura_save_threshold" class="button button-primary">
                    <?php _e('Guardar Configuración', 'aura-suite'); ?>
                </button>
            </p>
        </form>
        <?php
    }
    
    /**
     * Cargar datos para gráficos
     */
    private static function enqueue_chart_data() {
        $last_30_days = date('Y-m-d', strtotime('-30 days'));
        
        $readings = get_posts(array(
            'post_type'      => 'aura_electric_reading',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_key'       => '_aura_reading_date',
            'meta_query'     => array(
                array(
                    'key'     => '_aura_reading_date',
                    'value'   => $last_30_days,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            ),
        ));
        
        $labels = array();
        $data = array();
        
        for ($i = 1; $i < count($readings); $i++) {
            $current_kwh = floatval(get_post_meta($readings[$i]->ID, '_aura_reading_kwh', true));
            $previous_kwh = floatval(get_post_meta($readings[$i - 1]->ID, '_aura_reading_kwh', true));
            $date = get_post_meta($readings[$i]->ID, '_aura_reading_date', true);
            
            $labels[] = date_i18n('d M', strtotime($date));
            $data[] = $current_kwh - $previous_kwh;
        }
        
        $chart_data = array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label'           => __('Consumo Diario (kWh)', 'aura-suite'),
                    'data'            => $data,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor'     => 'rgb(59, 130, 246)',
                    'borderWidth'     => 2,
                    'tension'         => 0.4,
                ),
            ),
        );
        
        wp_localize_script('aura-charts', 'auraElectricityData', $chart_data);
    }
}
