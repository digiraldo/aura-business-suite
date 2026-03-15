<?php
/**
 * Clase para gestionar el Listado de Transacciones Pendientes de Aprobación
 * Extiende WP_List_Table para mostrar transacciones con status=pending
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
 * Clase Aura_Financial_Pending_List
 * 
 * Gestiona el listado de transacciones pendientes de aprobación
 */
class Aura_Financial_Pending_List extends WP_List_Table {
    
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
            'id'              => __('#', 'aura-suite'),
            'transaction_date'=> __('Fecha', 'aura-suite'),
            'type'            => __('Tipo', 'aura-suite'),
            'category'        => __('Categoría', 'aura-suite'),
            'description'     => __('Descripción', 'aura-suite'),
            'amount'          => __('Monto', 'aura-suite'),
            'created_by'      => __('Creado por', 'aura-suite'),
            'created_at'      => __('Registrado el', 'aura-suite'),
            'acciones'        => __('Acciones', 'aura-suite')
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
            'created_at' => array('created_at', true)
        );
    }
    
    /**
     * Acciones masivas
     */
    public function get_bulk_actions() {
        if (!current_user_can('aura_finance_approve')) {
            return array();
        }
        
        return array(
            'bulk_approve' => __('Aprobar seleccionadas', 'aura-suite'),
            'bulk_reject' => __('Rechazar seleccionadas', 'aura-suite')
        );
    }
    
    /**
     * Checkbox para selección
     */
    public function column_cb($item) {
        // Solo permitir checkbox si el usuario puede aprobar
        if (!current_user_can('aura_finance_approve')) {
            return '';
        }
        
        // No mostrar checkbox para propias transacciones
        if ($item['created_by'] == get_current_user_id()) {
            return '';
        }
        
        return sprintf(
            '<input type="checkbox" name="transaction_ids[]" value="%d" />',
            $item['id']
        );
    }
    
    /**
     * Columna Número (#ID) — para cruzar con notificaciones
     */
    public function column_id( $item ) {
        return sprintf(
            '<span style="font-family:monospace;font-size:12px;color:#555;font-weight:600;white-space:nowrap;display:inline-block;">#%d</span>',
            $item['id']
        );
    }

    /**
     * Columna: Fecha de transacción
     */
    public function column_transaction_date($item) {
        $date = new DateTime($item['transaction_date']);
        $now = new DateTime();
        $diff = $now->diff($date);
        
        $date_str = '<strong>' . $date->format('d/m/Y') . '</strong>';
        
        // Indicador de antigüedad
        if ($diff->days > 30) {
            $date_str .= ' <span class="old-pending" title="' . __('Más de 30 días pendiente', 'aura-suite') . '">⚠️</span>';
        } elseif ($diff->days > 7) {
            $date_str .= ' <span class="pending-warning" title="' . sprintf(__('%d días pendiente', 'aura-suite'), $diff->days) . '">⏰</span>';
        }
        
        return $date_str;
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
        
        $is_own_transaction = ($item['created_by'] == get_current_user_id());
        
        if (current_user_can('aura_finance_approve') && !$is_own_transaction) {
            $actions['approve'] = sprintf(
                '<a href="#" class="approve-transaction" data-id="%d" style="color: #10b981;">%s</a>',
                $item['id'],
                __('Aprobar', 'aura-suite')
            );
            
            $actions['reject'] = sprintf(
                '<a href="#" class="reject-transaction" data-id="%d" style="color: #e74c3c;">%s</a>',
                $item['id'],
                __('Rechazar', 'aura-suite')
            );
        }
        
        $actions['view'] = sprintf(
            '<a href="#" class="view-transaction" data-transaction-id="%d">%s</a>',
            $item['id'],
            __('Ver detalles', 'aura-suite')
        );
        
        if ($is_own_transaction && current_user_can('aura_finance_edit_own')) {
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=aura-financial-edit-transaction&id=' . $item['id']),
                __('Editar', 'aura-suite')
            );
        }
        
        if ($is_own_transaction) {
            $description .= ' <span class="own-transaction-label" title="' . __('Tu transacción', 'aura-suite') . '">👤</span>';
        }
        
        return '<strong>' . $description . '</strong>';
    }
    
    /**
     * Columna: Acciones
     */
    public function column_acciones($item) {
        return sprintf(
            '<button type="button" class="button button-small view-transaction" data-transaction-id="%d" style="white-space:nowrap;">'
            . '<span class="dashicons dashicons-visibility" style="font-size:14px;width:14px;height:14px;margin-right:4px;vertical-align:middle;"></span> %s'
            . '</button>',
            $item['id'],
            __('Ver Detalles', 'aura-suite')
        );
    }
    
    /**
     * Columna: Monto
     */
    public function column_amount($item) {
        $amount = floatval($item['amount']);
        $formatted = number_format($amount, 2, ',', '.');
        $class = $item['transaction_type'] === 'income' ? 'amount-income' : 'amount-expense';
        
        // Indicador de monto alto (>10,000)
        $high_amount_threshold = get_option('aura_finance_high_amount_threshold', 10000);
        $is_high_amount = $amount >= $high_amount_threshold;
        
        return sprintf(
            '<span class="transaction-amount %s">$%s %s</span>',
            $class,
            $formatted,
            $is_high_amount ? '<span class="high-amount-indicator" title="' . __('Monto alto', 'aura-suite') . '">💰</span>' : ''
        );
    }
    
    /**
     * Columna: Creado por
     */
    public function column_created_by($item) {
        $user = get_userdata($item['created_by']);
        
        if (!$user) {
            return '<em>' . __('Usuario desconocido', 'aura-suite') . '</em>';
        }
        
        $is_current_user = ($item['created_by'] == get_current_user_id());
        
        return sprintf(
            '<span class="creator-name %s">%s%s</span>',
            $is_current_user ? 'current-user' : '',
            esc_html($user->display_name),
            $is_current_user ? ' <span class="you-label">(' . __('Tú', 'aura-suite') . ')</span>' : ''
        );
    }
    
    /**
     * Columna: Registrado el
     */
    public function column_created_at($item) {
        $date = new DateTime($item['created_at']);
        return $date->format('d/m/Y H:i');
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
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'transaction_date';
        $order = isset($_REQUEST['order']) && in_array(strtoupper($_REQUEST['order']), array('ASC', 'DESC')) 
                 ? strtoupper($_REQUEST['order']) 
                 : 'DESC';
        
        // Filtros
        $where_clauses = array(
            "t.status = 'pending'",
            't.deleted_at IS NULL'
        );
        
        // Filtro de vista (tabs): Para aprobar vs Mis pendientes
        $filter_view = isset($_REQUEST['filter_view']) ? sanitize_text_field($_REQUEST['filter_view']) : 'all';
        if ($filter_view === 'others') {
            $where_clauses[] = $wpdb->prepare('t.created_by != %d', get_current_user_id());
        } elseif ($filter_view === 'mine') {
            $where_clauses[] = $wpdb->prepare('t.created_by = %d', get_current_user_id());
        }
        
        // Filtro por tipo
        if (!empty($_REQUEST['filter_type'])) {
            $type = sanitize_text_field($_REQUEST['filter_type']);
            if (in_array($type, array('income', 'expense'))) {
                $where_clauses[] = $wpdb->prepare('t.transaction_type = %s', $type);
            }
        }
        
        // Filtro por categoría
        if (!empty($_REQUEST['filter_category'])) {
            $category_id = absint($_REQUEST['filter_category']);
            $where_clauses[] = $wpdb->prepare('t.category_id = %d', $category_id);
        }
        
        // Filtro por creador
        if (!empty($_REQUEST['filter_creator'])) {
            $creator_id = absint($_REQUEST['filter_creator']);
            $where_clauses[] = $wpdb->prepare('t.created_by = %d', $creator_id);
        }
        
        // Filtro por rango de monto
        if (!empty($_REQUEST['filter_amount_min'])) {
            $where_clauses[] = $wpdb->prepare('t.amount >= %f', floatval($_REQUEST['filter_amount_min']));
        }
        if (!empty($_REQUEST['filter_amount_max'])) {
            $where_clauses[] = $wpdb->prepare('t.amount <= %f', floatval($_REQUEST['filter_amount_max']));
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
        _e('No hay transacciones pendientes de aprobación', 'aura-suite');
    }
    
    /**
     * Renderizar vistas de filtro (tabs)
     */
    public function get_views() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        $current_user_id = get_current_user_id();
        
        // Contar transacciones pendientes
        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'pending' AND deleted_at IS NULL"
        );
        
        $others = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = 'pending' AND deleted_at IS NULL AND created_by != %d",
            $current_user_id
        ));
        
        $mine = $total - $others;
        
        $views = array();
        $current = isset($_REQUEST['filter_view']) ? $_REQUEST['filter_view'] : 'all';
        
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            admin_url('admin.php?page=aura-financial-pending&filter_view=all'),
            $current === 'all' ? 'current' : '',
            __('Todas', 'aura-suite'),
            $total
        );
        
        if (current_user_can('aura_finance_approve')) {
            $views['others'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                admin_url('admin.php?page=aura-financial-pending&filter_view=others'),
                $current === 'others' ? 'current' : '',
                __('Para aprobar', 'aura-suite'),
                $others
            );
        }
        
        $views['mine'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            admin_url('admin.php?page=aura-financial-pending&filter_view=mine'),
            $current === 'mine' ? 'current' : '',
            __('Mis pendientes', 'aura-suite'),
            $mine
        );
        
        return $views;
    }
}
