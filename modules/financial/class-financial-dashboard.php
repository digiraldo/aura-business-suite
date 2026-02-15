<?php
/**
 * Dashboard Financiero
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar el dashboard financiero
 */
class Aura_Financial_Dashboard {
    
    /**
     * Renderizar el dashboard
     */
    public static function render() {
        // Verificar permisos
        if (!current_user_can('aura_finance_charts')) {
            wp_die(__('No tienes permiso para acceder a esta página.', 'aura-suite'));
        }
        
        ?>
        <div class="wrap aura-financial-dashboard">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-chart-line"></span>
                <?php _e('Dashboard Financiero', 'aura-suite'); ?>
            </h1>
            
            <?php self::render_filters(); ?>
            
            <div class="aura-dashboard-grid">
                <?php self::render_kpis(); ?>
                
                <div class="aura-chart-container">
                    <h2><?php _e('Ingresos vs Egresos Mensuales', 'aura-suite'); ?></h2>
                    <canvas id="aura-income-expense-chart"></canvas>
                </div>
                
                <div class="aura-chart-container">
                    <h2><?php _e('Distribución por Categorías', 'aura-suite'); ?></h2>
                    <canvas id="aura-category-chart"></canvas>
                </div>
                
                <?php if (current_user_can('aura_finance_view_all')): ?>
                <div class="aura-transactions-table">
                    <h2><?php _e('Transacciones Recientes', 'aura-suite'); ?></h2>
                    <?php self::render_recent_transactions(); ?>
                </div>
                <?php endif; ?>
                
                <?php if (current_user_can('aura_finance_approve')): ?>
                <div class="aura-pending-approvals">
                    <h2><?php _e('Pendientes de Aprobación', 'aura-suite'); ?></h2>
                    <?php self::render_pending_approvals(); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (current_user_can('aura_finance_export')): ?>
            <div class="aura-export-section">
                <button id="aura-export-pdf" class="button button-secondary">
                    <span class="dashicons dashicons-pdf"></span>
                    <?php _e('Exportar a PDF', 'aura-suite'); ?>
                </button>
                <button id="aura-export-excel" class="button button-secondary">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <?php _e('Exportar a Excel', 'aura-suite'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php
        
        // Cargar datos para gráficos
        self::enqueue_chart_data();
    }
    
    /**
     * Renderizar filtros
     */
    private static function render_filters() {
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        ?>
        <div class="aura-filters-box">
            <form method="get" action="">
                <input type="hidden" name="page" value="aura-financial-dashboard">
                
                <label for="start_date"><?php _e('Desde:', 'aura-suite'); ?></label>
                <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                
                <label for="end_date"><?php _e('Hasta:', 'aura-suite'); ?></label>
                <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                
                <label for="category"><?php _e('Categoría:', 'aura-suite'); ?></label>
                <select id="category" name="category">
                    <option value=""><?php _e('Todas', 'aura-suite'); ?></option>
                    <?php
                    $categories = get_terms(array('taxonomy' => 'aura_transaction_category', 'hide_empty' => false));
                    foreach ($categories as $cat) {
                        printf(
                            '<option value="%s"%s>%s</option>',
                            esc_attr($cat->slug),
                            selected($category, $cat->slug, false),
                            esc_html($cat->name)
                        );
                    }
                    ?>
                </select>
                
                <label for="status"><?php _e('Estado:', 'aura-suite'); ?></label>
                <select id="status" name="status">
                    <option value=""><?php _e('Todos', 'aura-suite'); ?></option>
                    <option value="draft"<?php selected($status, 'draft'); ?>><?php _e('Borrador', 'aura-suite'); ?></option>
                    <option value="pending"<?php selected($status, 'pending'); ?>><?php _e('Pendiente', 'aura-suite'); ?></option>
                    <option value="approved"<?php selected($status, 'approved'); ?>><?php _e('Aprobada', 'aura-suite'); ?></option>
                    <option value="rejected"<?php selected($status, 'rejected'); ?>><?php _e('Rechazada', 'aura-suite'); ?></option>
                </select>
                
                <button type="submit" class="button button-primary">
                    <?php _e('Filtrar', 'aura-suite'); ?>
                </button>
                <a href="?page=aura-financial-dashboard" class="button">
                    <?php _e('Limpiar', 'aura-suite'); ?>
                </a>
            </form>
        </div>
        <?php
    }
    
    /**
     * Renderizar KPIs
     */
    private static function render_kpis() {
        $data = self::get_dashboard_data();
        
        ?>
        <div class="aura-kpis-grid">
            <?php if (current_user_can('aura_finance_view_all')): ?>
                <div class="aura-kpi-card income">
                    <div class="kpi-icon">💰</div>
                    <div class="kpi-content">
                        <h3><?php _e('Total Ingresos', 'aura-suite'); ?></h3>
                        <p class="kpi-value">$<?php echo number_format($data['total_income'], 2); ?></p>
                    </div>
                </div>
                
                <div class="aura-kpi-card expense">
                    <div class="kpi-icon">💸</div>
                    <div class="kpi-content">
                        <h3><?php _e('Total Egresos', 'aura-suite'); ?></h3>
                        <p class="kpi-value">$<?php echo number_format($data['total_expense'], 2); ?></p>
                    </div>
                </div>
                
                <div class="aura-kpi-card balance">
                    <div class="kpi-icon">📊</div>
                    <div class="kpi-content">
                        <h3><?php _e('Saldo', 'aura-suite'); ?></h3>
                        <p class="kpi-value">$<?php echo number_format($data['balance'], 2); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (current_user_can('aura_finance_approve')): ?>
                <div class="aura-kpi-card pending">
                    <div class="kpi-icon">⏳</div>
                    <div class="kpi-content">
                        <h3><?php _e('Pendientes de Aprobación', 'aura-suite'); ?></h3>
                        <p class="kpi-value"><?php echo $data['pending_count']; ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (current_user_can('aura_finance_view_own') && !current_user_can('aura_finance_view_all')): ?>
                <div class="aura-kpi-card own">
                    <div class="kpi-icon">📝</div>
                    <div class="kpi-content">
                        <h3><?php _e('Mis Transacciones', 'aura-suite'); ?></h3>
                        <p class="kpi-value"><?php echo $data['own_count']; ?></p>
                    </div>
                </div>
                
                <div class="aura-kpi-card approved">
                    <div class="kpi-icon">✅</div>
                    <div class="kpi-content">
                        <h3><?php _e('Aprobadas este Mes', 'aura-suite'); ?></h3>
                        <p class="kpi-value"><?php echo $data['own_approved']; ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Obtener datos del dashboard
     */
    private static function get_dashboard_data() {
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        $args = array(
            'post_type'      => 'aura_transaction',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_aura_transaction_date',
                    'value'   => array($start_date, $end_date),
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE',
                ),
            ),
        );
        
        // Filtrar por autor si solo puede ver propias
        if (!current_user_can('aura_finance_view_all') && current_user_can('aura_finance_view_own')) {
            $args['author'] = get_current_user_id();
        }
        
        // Filtrar por categoría
        if ($category) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'aura_transaction_category',
                    'field'    => 'slug',
                    'terms'    => $category,
                ),
            );
        }
        
        // Filtrar por estado
        if ($status) {
            $args['meta_query'][] = array(
                'key'   => '_aura_transaction_status',
                'value' => $status,
            );
        }
        
        $transactions = get_posts($args);
        
        $data = array(
            'total_income'   => 0,
            'total_expense'  => 0,
            'balance'        => 0,
            'pending_count'  => 0,
            'own_count'      => 0,
            'own_approved'   => 0,
        );
        
        foreach ($transactions as $transaction) {
            $amount = floatval(get_post_meta($transaction->ID, '_aura_transaction_amount', true));
            $trans_status = get_post_meta($transaction->ID, '_aura_transaction_status', true);
            $types = wp_get_post_terms($transaction->ID, 'aura_transaction_type');
            $type = !empty($types) ? $types[0]->slug : '';
            
            if ($type === 'income' && $trans_status === 'approved') {
                $data['total_income'] += $amount;
            } elseif ($type === 'expense' && $trans_status === 'approved') {
                $data['total_expense'] += $amount;
            }
            
            if ($trans_status === 'pending') {
                $data['pending_count']++;
            }
            
            if ($transaction->post_author == get_current_user_id()) {
                $data['own_count']++;
                if ($trans_status === 'approved') {
                    $data['own_approved']++;
                }
            }
        }
        
        $data['balance'] = $data['total_income'] - $data['total_expense'];
        
        return $data;
    }
    
    /**
     * Renderizar transacciones recientes
     */
    private static function render_recent_transactions() {
        $args = array(
            'post_type'      => 'aura_transaction',
            'posts_per_page' => 10,
            'orderby'        => 'meta_value',
            'meta_key'       => '_aura_transaction_date',
            'order'          => 'DESC',
        );
        
        $transactions = get_posts($args);
        
        if (empty($transactions)) {
            echo '<p>' . __('No hay transacciones recientes.', 'aura-suite') . '</p>';
            return;
        }
        
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Fecha', 'aura-suite'); ?></th>
                    <th><?php _e('Descripción', 'aura-suite'); ?></th>
                    <th><?php _e('Tipo', 'aura-suite'); ?></th>
                    <th><?php _e('Monto', 'aura-suite'); ?></th>
                    <th><?php _e('Estado', 'aura-suite'); ?></th>
                    <th><?php _e('Acciones', 'aura-suite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction): 
                    $amount = get_post_meta($transaction->ID, '_aura_transaction_amount', true);
                    $date = get_post_meta($transaction->ID, '_aura_transaction_date', true);
                    $status = get_post_meta($transaction->ID, '_aura_transaction_status', true);
                    $types = wp_get_post_terms($transaction->ID, 'aura_transaction_type');
                    $type = !empty($types) ? $types[0]->name : '—';
                ?>
                <tr>
                    <td><?php echo date_i18n(get_option('date_format'), strtotime($date)); ?></td>
                    <td><?php echo esc_html($transaction->post_title); ?></td>
                    <td><?php echo esc_html($type); ?></td>
                    <td>$<?php echo number_format($amount, 2); ?></td>
                    <td><span class="status-badge status-<?php echo esc_attr($status); ?>">
                        <?php 
                        $statuses = array('draft' => __('Borrador', 'aura-suite'), 'pending' => __('Pendiente', 'aura-suite'), 
                                         'approved' => __('Aprobada', 'aura-suite'), 'rejected' => __('Rechazada', 'aura-suite'));
                        echo $statuses[$status] ?? __('Borrador', 'aura-suite'); 
                        ?>
                    </span></td>
                    <td>
                        <a href="<?php echo admin_url('post.php?post=' . $transaction->ID . '&action=edit'); ?>" 
                           class="button button-small">
                            <?php _e('Ver', 'aura-suite'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Renderizar transacciones pendientes de aprobación
     */
    private static function render_pending_approvals() {
        $args = array(
            'post_type'      => 'aura_transaction',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_aura_transaction_status',
                    'value' => 'pending',
                ),
            ),
        );
        
        $transactions = get_posts($args);
        
        if (empty($transactions)) {
            echo '<p>' . __('No hay transacciones pendientes de aprobación.', 'aura-suite') . '</p>';
            return;
        }
        
        foreach ($transactions as $transaction) {
            $amount = get_post_meta($transaction->ID, '_aura_transaction_amount', true);
            $date = get_post_meta($transaction->ID, '_aura_transaction_date', true);
            $author = get_user_by('id', $transaction->post_author);
            $days_pending = floor((time() - strtotime($transaction->post_date)) / 86400);
            
            $urgency_class = $days_pending > 3 ? 'urgent' : '';
            
            ?>
            <div class="aura-pending-card <?php echo $urgency_class; ?>">
                <div class="pending-header">
                    <h3><?php echo esc_html($transaction->post_title); ?></h3>
                    <?php if ($days_pending > 3): ?>
                        <span class="badge-urgent"><?php printf(__('Hace %d días', 'aura-suite'), $days_pending); ?></span>
                    <?php endif; ?>
                </div>
                <p><strong><?php _e('Solicitante:', 'aura-suite'); ?></strong> <?php echo $author->display_name; ?></p>
                <p><strong><?php _e('Monto:', 'aura-suite'); ?></strong> $<?php echo number_format($amount, 2); ?></p>
                <p><strong><?php _e('Fecha:', 'aura-suite'); ?></strong> <?php echo date_i18n(get_option('date_format'), strtotime($date)); ?></p>
                <a href="<?php echo admin_url('post.php?post=' . $transaction->ID . '&action=edit'); ?>" 
                   class="button button-primary">
                    <?php _e('Revisar y Aprobar', 'aura-suite'); ?>
                </a>
            </div>
            <?php
        }
    }
    
    /**
     * Encolar datos para gráficos
     */
    private static function enqueue_chart_data() {
        $chart_data = Aura_Financial_Charts::get_chart_data();
        
        wp_localize_script('aura-charts', 'auraChartData', $chart_data);
    }
}
