<?php
/**
 * Gráficos Financieros
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar gráficos financieros
 */
class Aura_Financial_Charts {
    
    /**
     * Obtener datos para gráficos
     * 
     * @return array Datos formateados para Chart.js
     */
    public static function get_chart_data() {
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');
        
        return array(
            'incomeExpense' => self::get_income_expense_data($start_date, $end_date),
            'categories'    => self::get_category_distribution_data($start_date, $end_date),
        );
    }
    
    /**
     * Obtener datos de ingresos vs egresos
     * 
     * @param string $start_date Fecha inicio
     * @param string $end_date Fecha fin
     * @return array
     */
    private static function get_income_expense_data($start_date, $end_date) {
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
                array(
                    'key'   => '_aura_transaction_status',
                    'value' => 'approved',
                ),
            ),
        );
        
        if (!current_user_can('aura_finance_view_all') && current_user_can('aura_finance_view_own')) {
            $args['author'] = get_current_user_id();
        }
        
        $transactions = get_posts($args);
        
        // Agrupar por mes
        $data_by_month = array();
        
        foreach ($transactions as $transaction) {
            $amount = floatval(get_post_meta($transaction->ID, '_aura_transaction_amount', true));
            $date = get_post_meta($transaction->ID, '_aura_transaction_date', true);
            $month = date('Y-m', strtotime($date));
            $types = wp_get_post_terms($transaction->ID, 'aura_transaction_type');
            $type = !empty($types) ? $types[0]->slug : '';
            
            if (!isset($data_by_month[$month])) {
                $data_by_month[$month] = array('income' => 0, 'expense' => 0);
            }
            
            if ($type === 'income') {
                $data_by_month[$month]['income'] += $amount;
            } elseif ($type === 'expense') {
                $data_by_month[$month]['expense'] += $amount;
            }
        }
        
        ksort($data_by_month);
        
        $labels = array();
        $income_data = array();
        $expense_data = array();
        
        foreach ($data_by_month as $month => $values) {
            $labels[] = date_i18n('M Y', strtotime($month . '-01'));
            $income_data[] = $values['income'];
            $expense_data[] = $values['expense'];
        }
        
        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label'           => __('Ingresos', 'aura-suite'),
                    'data'            => $income_data,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                    'borderColor'     => 'rgb(16, 185, 129)',
                    'borderWidth'     => 2,
                ),
                array(
                    'label'           => __('Egresos', 'aura-suite'),
                    'data'            => $expense_data,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.5)',
                    'borderColor'     => 'rgb(239, 68, 68)',
                    'borderWidth'     => 2,
                ),
            ),
        );
    }
    
    /**
     * Obtener distribución por categorías
     * 
     * @param string $start_date Fecha inicio
     * @param string $end_date Fecha fin
     * @return array
     */
    private static function get_category_distribution_data($start_date, $end_date) {
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
                array(
                    'key'   => '_aura_transaction_status',
                    'value' => 'approved',
                ),
            ),
        );
        
        if (!current_user_can('aura_finance_view_all') && current_user_can('aura_finance_view_own')) {
            $args['author'] = get_current_user_id();
        }
        
        $transactions = get_posts($args);
        
        $data_by_category = array();
        
        foreach ($transactions as $transaction) {
            $amount = floatval(get_post_meta($transaction->ID, '_aura_transaction_amount', true));
            $categories = wp_get_post_terms($transaction->ID, 'aura_transaction_category');
            $category = !empty($categories) ? $categories[0]->name : __('Sin categoría', 'aura-suite');
            
            if (!isset($data_by_category[$category])) {
                $data_by_category[$category] = 0;
            }
            
            $data_by_category[$category] += $amount;
        }
        
        arsort($data_by_category);
        
        $colors = array(
            'rgba(102, 126, 234, 0.8)',
            'rgba(118, 75, 162, 0.8)',
            'rgba(237, 100, 166, 0.8)',
            'rgba(255, 154, 158, 0.8)',
            'rgba(250, 208, 196, 0.8)',
            'rgba(212, 163, 115, 0.8)',
        );
        
        return array(
            'labels' => array_keys($data_by_category),
            'datasets' => array(
                array(
                    'data'            => array_values($data_by_category),
                    'backgroundColor' => array_slice($colors, 0, count($data_by_category)),
                    'borderWidth'     => 2,
                    'borderColor'     => '#ffffff',
                ),
            ),
        );
    }
}
