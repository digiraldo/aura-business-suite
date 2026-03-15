<?php
/**
 * Clase para gestionar la Papelera de Transacciones Financieras
 * Extiende WP_List_Table para mostrar transacciones eliminadas
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar WP_List_Table si no está disponible
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Clase Aura_Financial_Trash_List
 * 
 * Gestiona el listado de transacciones eliminadas (papelera)
 */
class Aura_Financial_Trash_List extends WP_List_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'transacción',
            'plural'   => 'transacciones',
            'ajax'     => false
        ));
    }
    
    /**
     * Obtener columnas de la tabla
     */
    public function get_columns() {
        $columns = array(
            'cb'              => '<input type="checkbox" />',
            'transaction_date'=> __('Fecha', 'aura-suite'),
            'type'            => __('Tipo', 'aura-suite'),
            'category'        => __('Categoría', 'aura-suite'),
            'description'     => __('Descripción', 'aura-suite'),
            'amount'          => __('Monto', 'aura-suite'),
            'status'          => __('Estado', 'aura-suite'),
            'deleted_at'      => __('Eliminado el', 'aura-suite'),
            'deleted_by'      => __('Eliminado por', 'aura-suite')
        );
        
        return $columns;
    }
    
    /**
     * Columnas sortables
     */
    public function get_sortable_columns() {
        return array(
            'transaction_date' => array('transaction_date', true),
            'amount' => array('amount', false),
            'deleted_at' => array('deleted_at', true)
        );
    }
    
    /**
     * Acciones masivas
     */
    public function get_bulk_actions() {
        $actions = array(
            'bulk_restore' => __('Restaurar', 'aura-suite')
        );
        
        if (current_user_can('manage_options')) {
            $actions['bulk_permanent_delete'] = __('Eliminar permanentemente', 'aura-suite');
        }
        
        return $actions;
    }
    
    /**
     * Checkbox para selección
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="transaction_ids[]" value="%d" />',
            $item['id']
        );
    }
    
    /**
     * Columna: Fecha de transacción
     */
    public function column_transaction_date($item) {
        $date = new DateTime($item['transaction_date']);
        return '<strong>' . $date->format('d/m/Y') . '</strong>';
    }
    
    /**
     * Columna: Tipo
     */
    public function column_type($item) {
        $type_labels = array(
            'income' => __('Ingreso', 'aura-suite'),
            'expense' => __('Egreso', 'aura-suite')
        );
        
        $type_class = $item['transaction_type'] === 'income' ? 'type-income' : 'type-expense';
        $icon = $item['transaction_type'] === 'income' ? 'arrow-down-alt' : 'arrow-up-alt';
        
        return sprintf(
            '<span class="transaction-type %s"><span class="dashicons dashicons-%s"></span> %s</span>',
            $type_class,
            $icon,
            isset($type_labels[$item['transaction_type']]) ? $type_labels[$item['transaction_type']] : $item['transaction_type']
        );
    }
    
    /**
     * Columna: Categoría
     */
    public function column_category($item) {
        if (empty($item['category_name'])) {
            return '<em>' . __('Sin categoría', 'aura-suite') . '</em>';
        }
        
        $color = !empty($item['category_color']) ? $item['category_color'] : '#cccccc';
        $icon = !empty($item['category_icon']) ? $item['category_icon'] : 'tag';
        
        return sprintf(
            '<span class="category-badge" style="border-left-color: %s;">
                <span class="dashicons dashicons-%s"></span> %s
            </span>',
            esc_attr($color),
            esc_attr($icon),
            esc_html($item['category_name'])
        );
    }
    
    /**
     * Columna: Descripción
     */
    public function column_description($item) {
        $description = esc_html($item['description']);
        
        // Acciones de fila
        $actions = array();
        
        if (Aura_Financial_Transactions_Delete::can_restore_transaction($item['id'])) {
            $actions['restore'] = sprintf(
                '<a href="#" class="restore-transaction" data-id="%d">%s</a>',
                $item['id'],
                __('Restaurar', 'aura-suite')
            );
        }
        
        $actions['view'] = sprintf(
            '<a href="#" class="view-transaction" data-id="%d">%s</a>',
            $item['id'],
            __('Ver detalles', 'aura-suite')
        );
        
        if (current_user_can('manage_options')) {
            $actions['permanent_delete'] = sprintf(
                '<a href="#" class="permanent-delete-transaction" data-id="%d" style="color: #b32d2e;">%s</a>',
                $item['id'],
                __('Eliminar permanentemente', 'aura-suite')
            );
        }
        
        return '<strong>' . $description . '</strong>' . $this->row_actions($actions);
    }
    
    /**
     * Columna: Monto
     */
    public function column_amount($item) {
        $amount = floatval($item['amount']);
        $formatted = number_format($amount, 2, ',', '.');
        $class = $item['transaction_type'] === 'income' ? 'amount-income' : 'amount-expense';
        
        return sprintf(
            '<span class="transaction-amount %s">$%s</span>',
            $class,
            $formatted
        );
    }
    
    /**
     * Columna: Estado
     */
    public function column_status($item) {
        $status_labels = array(
            'pending' => __('Pendiente', 'aura-suite'),
            'approved' => __('Aprobado', 'aura-suite'),
            'rejected' => __('Rechazado', 'aura-suite')
        );
        
        $status_class = 'status-' . $item['status'];
        
        return sprintf(
            '<span class="status-badge %s">%s</span>',
            $status_class,
            isset($status_labels[$item['status']]) ? $status_labels[$item['status']] : $item['status']
        );
    }
    
    /**
     * Columna: Eliminado el
     */
    public function column_deleted_at($item) {
        if (empty($item['deleted_at'])) {
            return '-';
        }
        
        $date = new DateTime($item['deleted_at']);
        $now = new DateTime();
        $diff = $now->diff($date);
        
        // Calcular días restantes antes de eliminación permanente
        $days_in_trash = $diff->days;
        $days_remaining = Aura_Financial_Transactions_Delete::TRASH_RETENTION_DAYS - $days_in_trash;
        
        $deleted_info = $date->format('d/m/Y H:i');
        
        if ($days_remaining <= 0) {
            $deleted_info .= '<br><span style="color: #b32d2e; font-size: 11px;">⚠️ ' . __('Se eliminará hoy', 'aura-suite') . '</span>';
        } elseif ($days_remaining <= 7) {
            $deleted_info .= sprintf(
                '<br><span style="color: #f39c12; font-size: 11px;">⏰ %s</span>',
                sprintf(_n('Queda %d día', 'Quedan %d días', $days_remaining, 'aura-suite'), $days_remaining)
            );
        }
        
        return $deleted_info;
    }
    
    /**
     * Columna: Eliminado por
     */
    public function column_deleted_by($item) {
        if (!empty($item['deleted_by'])) {
            $user = get_userdata($item['deleted_by']);
            return $user ? esc_html($user->display_name) : __('Usuario eliminado', 'aura-suite');
        }
        
        // Fallback: buscar en el historial (para transacciones antiguas sin deleted_by)
        global $wpdb;
        $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
        
        $deleted_by = $wpdb->get_var($wpdb->prepare(
            "SELECT changed_by FROM $history_table 
             WHERE transaction_id = %d 
             AND field_changed = 'status_deletion'
             ORDER BY changed_at DESC
             LIMIT 1",
            $item['id']
        ));
        
        if ($deleted_by) {
            $user = get_userdata($deleted_by);
            return $user ? esc_html($user->display_name) : __('Usuario eliminado', 'aura-suite');
        }
        
        return '-';
    }
    
    /**
     * Preparar items para mostrar
     */
    public function prepare_items() {
        global $wpdb;
        
        // Configurar columnas
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Parámetros de paginación y ordenamiento
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'deleted_at';
        $order = isset($_REQUEST['order']) && in_array(strtoupper($_REQUEST['order']), array('ASC', 'DESC')) 
                 ? strtoupper($_REQUEST['order']) 
                 : 'DESC';
        
        // Filtros
        $where_clauses = array('t.deleted_at IS NOT NULL'); // Solo transacciones eliminadas
        
        // Filtro por tipo
        if (!empty($_REQUEST['filter_type'])) {
            $type = sanitize_text_field($_REQUEST['filter_type']);
            if (in_array($type, array('income', 'expense'))) {
                $where_clauses[] = $wpdb->prepare('t.transaction_type = %s', $type);
            }
        }
        
        // Filtro por estado
        if (!empty($_REQUEST['filter_status'])) {
            $status = sanitize_text_field($_REQUEST['filter_status']);
            if (in_array($status, array('pending', 'approved', 'rejected'))) {
                $where_clauses[] = $wpdb->prepare('t.status = %s', $status);
            }
        }
        
        // Búsqueda
        if (!empty($_REQUEST['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['s'])) . '%';
            $where_clauses[] = $wpdb->prepare(
                '(t.description LIKE %s OR t.reference_number LIKE %s OR t.recipient_payer LIKE %s)',
                $search, $search, $search
            );
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Tabla de transacciones
        $table = $wpdb->prefix . 'aura_finance_transactions';
        $categories_table = $wpdb->prefix . 'aura_finance_categories';
        
        // Contar total de items
        $total_items = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table t WHERE $where_sql"
        );
        
        // Obtener items
        $offset = ($current_page - 1) * $per_page;
        
        $query = "SELECT t.*, c.name as category_name, c.color as category_color, c.icon as category_icon
                  FROM $table t
                  LEFT JOIN $categories_table c ON t.category_id = c.id
                  WHERE $where_sql
                  ORDER BY t.$orderby $order
                  LIMIT %d OFFSET %d";
        
        $this->items = $wpdb->get_results(
            $wpdb->prepare($query, $per_page, $offset),
            ARRAY_A
        );
        
        // Configurar paginación
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
    
    /**
     * Mensaje cuando no hay items
     */
    public function no_items() {
        _e('No hay transacciones en la papelera', 'aura-suite');
    }
    
    /**
     * Renderizar vistas de filtro (tab)
     */
    public function get_views() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Contar transacciones en papelera
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE deleted_at IS NOT NULL");
        
        $views = array();
        $current = '';
        
        $views['all'] = sprintf(
            '<a href="%s" class="current">%s <span class="count">(%d)</span></a>',
            admin_url('admin.php?page=aura-financial-trash'),
            __('Todas', 'aura-suite'),
            $total
        );
        
        return $views;
    }
}
