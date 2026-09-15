<?php
/**
 * Clase para gestionar la Papelera de Transacciones Financieras
 * Extiende Aura_List_Table para soporte nativo de Filas Expandibles (Child Rows) y diseño Responsive
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar clase base de Aura si no está disponible
if (!class_exists('Aura_List_Table')) {
    require_once AURA_PLUGIN_DIR . 'modules/common/class-aura-list-table.php';
}

/**
 * Clase Aura_Financial_Trash_List
 * 
 * Gestiona el listado de transacciones eliminadas (papelera) con Child Rows y Responsive
 */
class Aura_Financial_Trash_List extends Aura_List_Table {
    
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
            'amount'           => array('amount', false),
            'deleted_at'       => array('deleted_at', true)
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
     * Columna: #ID con Toggle de Child Rows
     */
    public function column_id($item) {
        $toggle = $this->render_row_toggle($item['id']);
        return $toggle . sprintf(
            '<span class="aura-txn-id">#%d</span>',
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
        
        $color = !empty($item['category_color']) ? $item['category_color'] : '#64748b';
        $icon  = !empty($item['category_icon']) ? $item['category_icon'] : '📁';
        $icon_html = class_exists('Aura_Financial_Categories') ? Aura_Financial_Categories::render_icon_html($icon, $color) : '';
        
        return sprintf(
            '<span class="category-badge" style="display:inline-flex; align-items:center; gap:4px;">
                %s <span>%s</span>
            </span>',
            $icon_html,
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
                '<a href="#" class="permanent-delete-transaction" data-id="%d" style="color: #dc2626;">%s</a>',
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
            'pending'  => __('Pendiente', 'aura-suite'),
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
            $deleted_info .= ' <span class="aura-tooltip-wrap"><span class="aura-help-icon" style="color:#dc2626;"><span class="dashicons dashicons-warning"></span></span><span class="aura-tooltip-box">' . esc_html__('Se eliminará definitivamente hoy', 'aura-suite') . '</span></span>';
        } elseif ($days_remaining <= 7) {
            $deleted_info .= ' <span class="aura-tooltip-wrap"><span class="aura-help-icon" style="color:#f59e0b;"><span class="dashicons dashicons-clock"></span></span><span class="aura-tooltip-box">' . sprintf(esc_html__('Quedan %d días para eliminación automática', 'aura-suite'), $days_remaining) . '</span></span>';
        }
        
        return $deleted_info;
    }
    
    /**
     * Columna: Eliminado por
     */
    public function column_deleted_by($item) {
        if (empty($item['deleted_by'])) {
            return '<em>' . __('Desconocido', 'aura-suite') . '</em>';
        }
        
        $user = get_userdata($item['deleted_by']);
        
        if (!$user) {
            return '<em>' . __('Usuario eliminado', 'aura-suite') . '</em>';
        }
        
        $avatar_url = get_avatar_url($user->ID, array('size' => 24));
        $is_current = ($item['deleted_by'] == get_current_user_id());
        
        return sprintf(
            '<div class="aura-user-badge-card" style="display:inline-flex;align-items:center;gap:6px;">
                <img src="%s" width="22" height="22" style="border-radius:50%%;" />
                <span class="user-name">%s</span>
                %s
            </div>',
            esc_url($avatar_url),
            esc_html($user->display_name),
            $is_current ? ' <span class="you-label" style="font-size:10px;background:#eff6ff;color:#2563eb;padding:1px 5px;border-radius:10px;font-weight:700;">' . __('Tú', 'aura-suite') . '</span>' : ''
        );
    }
    
    /**
     * Fila Hija Expandible (Child Row)
     *
     * @param object|array $item
     */
    protected function render_child_row_content($item) {
        $is_income       = ($item['transaction_type'] === 'income');
        $date            = new DateTime($item['transaction_date']);
        $deleted_date    = !empty($item['deleted_at']) ? new DateTime($item['deleted_at']) : null;
        $created_date    = !empty($item['created_at']) ? new DateTime($item['created_at']) : null;
        $now             = new DateTime();
        $diff            = $deleted_date ? $now->diff($deleted_date) : null;
        $days_remaining  = $diff ? (Aura_Financial_Transactions_Delete::TRASH_RETENTION_DAYS - $diff->days) : 0;
        $amount_fmt      = '$' . number_format((float)$item['amount'], 2, ',', '.');
        
        $deleted_user    = !empty($item['deleted_by']) ? get_userdata($item['deleted_by']) : null;
        $deleted_by_name = $deleted_user ? $deleted_user->display_name : __('Desconocido', 'aura-suite');
        $deleted_avatar  = $deleted_user ? get_avatar_url($deleted_user->ID, array('size' => 36)) : '';
        
        $created_user    = !empty($item['created_by']) ? get_userdata($item['created_by']) : null;
        $created_by_name = $created_user ? $created_user->display_name : __('Desconocido', 'aura-suite');
        ?>
        <!-- Hero Bar Superior -->
        <div class="aura-child-hero-bar">
            <div class="aura-child-hero-left">
                <div class="aura-type-pill <?php echo $is_income ? 'income' : 'expense'; ?>">
                    <?php echo $is_income ? '↙' : '↗'; ?>
                </div>
                <div class="aura-child-hero-titles">
                    <h3 class="aura-child-hero-title">
                        <?php echo esc_html($item['description']); ?>
                        <span class="aura-code-pill">#<?php echo esc_html($item['id']); ?></span>
                    </h3>
                    <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap;">
                        <span class="aura-pill-badge" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;">
                            <span class="dashicons dashicons-trash"></span> <?php esc_html_e('En Papelera', 'aura-suite'); ?>
                        </span>
                        <span class="aura-pill-badge" style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;">
                            <span class="dashicons dashicons-calendar-alt"></span> <?php echo esc_html($date->format('d/m/Y')); ?>
                        </span>
                        <?php if (!empty($item['category_name'])): ?>
                        <span class="aura-pill-badge" style="background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;">
                            <span class="dashicons dashicons-tag"></span> <?php echo esc_html($item['category_name']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="aura-child-hero-right">
                <div class="aura-child-amount-card" style="text-align:right;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">
                        <?php esc_html_e('Monto Eliminado', 'aura-suite'); ?>
                    </span>
                    <span class="aura-ud-amount <?php echo $is_income ? 'income' : 'expense'; ?>" style="font-size:18px;font-weight:800;">
                        <?php echo $is_income ? '+' : '-'; ?><?php echo esc_html($amount_fmt); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Grid de Sub-Tarjetas -->
        <div class="aura-child-sections-grid">

            <!-- Sub-Card 1: Datos de la Transacción -->
            <div class="aura-child-subcard">
                <div class="aura-subcard-header">
                    <span class="dashicons dashicons-info aura-subcard-icon"></span>
                    <h4><?php esc_html_e('Detalles Financieros', 'aura-suite'); ?></h4>
                </div>
                <div class="aura-subcard-body">
                    <?php echo self::render_meta_item(__('Tipo de Operación', 'aura-suite'), $is_income ? __('Ingreso', 'aura-suite') : __('Egreso', 'aura-suite'), 'dashicons-randomize'); ?>
                    <?php echo self::render_meta_item(__('Categoría', 'aura-suite'), !empty($item['category_name']) ? esc_html($item['category_name']) : __('Sin categoría', 'aura-suite'), 'dashicons-category'); ?>
                    <?php if (!empty($item['reference_number'])): ?>
                    <?php echo self::render_meta_item(__('Referencia / Folio', 'aura-suite'), '<span class="aura-code-pill">' . esc_html($item['reference_number']) . '</span>', 'dashicons-media-document'); ?>
                    <?php endif; ?>
                    <?php if (!empty($item['recipient_payer'])): ?>
                    <?php echo self::render_meta_item(__('Tercero Relacionado', 'aura-suite'), esc_html($item['recipient_payer']), 'dashicons-businessperson'); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sub-Card 2: Datos de Eliminación -->
            <div class="aura-child-subcard">
                <div class="aura-subcard-header">
                    <span class="dashicons dashicons-trash aura-subcard-icon" style="color:#dc2626;"></span>
                    <h4><?php esc_html_e('Eliminado Por', 'aura-suite'); ?></h4>
                </div>
                <div class="aura-subcard-body">
                    <div class="aura-user-badge-card" style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                        <?php if ($deleted_avatar): ?>
                        <img src="<?php echo esc_url($deleted_avatar); ?>" width="32" height="32" style="border-radius:50%;" />
                        <?php endif; ?>
                        <div class="aura-user-info">
                            <strong class="aura-user-name"><?php echo esc_html($deleted_by_name); ?></strong>
                            <div class="aura-user-sub" style="font-size:11px;color:#64748b;">
                                <?php echo $deleted_date ? esc_html($deleted_date->format('d/m/Y H:i')) : '-'; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($item['delete_reason'])): ?>
                    <?php echo self::render_meta_item(__('Motivo de Borrado', 'aura-suite'), esc_html($item['delete_reason']), 'dashicons-warning'); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sub-Card 3: Retención y Ciclo de Vida -->
            <div class="aura-child-subcard">
                <div class="aura-subcard-header">
                    <span class="dashicons dashicons-backup aura-subcard-icon"></span>
                    <h4><?php esc_html_e('Retención en Papelera', 'aura-suite'); ?></h4>
                </div>
                <div class="aura-subcard-body">
                    <div style="font-size:12px;color:#475569;margin-bottom:6px;">
                        <?php if ($days_remaining <= 0): ?>
                        <span style="color:#dc2626;font-weight:700;">⚠️ <?php esc_html_e('Programada para eliminación definitiva hoy.', 'aura-suite'); ?></span>
                        <?php else: ?>
                        <?php printf(esc_html__('Quedan %d días antes del borrado permanente automático.', 'aura-suite'), $days_remaining); ?>
                        <?php endif; ?>
                    </div>
                    <?php echo self::render_meta_item(__('Registrado originalmente', 'aura-suite'), $created_date ? esc_html($created_date->format('d/m/Y')) : '-', 'dashicons-calendar'); ?>
                    <?php echo self::render_meta_item(__('Creado por', 'aura-suite'), esc_html($created_by_name), 'dashicons-admin-users'); ?>
                </div>
            </div>

        </div>

        <!-- Dock de Acciones Rápidas -->
        <div class="aura-child-action-dock" style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;padding-top:10px;border-top:1px solid #e2e8f0;flex-wrap:wrap;gap:8px;">
            <div class="aura-action-dock-left">
                <button type="button" class="button button-secondary view-transaction aura-btn-dock" data-id="<?php echo esc_attr($item['id']); ?>">
                    <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Ver Modal Completo', 'aura-suite'); ?>
                </button>
            </div>
            <div class="aura-action-dock-right" style="display:flex;gap:6px;">
                <?php if (Aura_Financial_Transactions_Delete::can_restore_transaction($item['id'])): ?>
                <button type="button" class="button button-primary restore-transaction aura-btn-dock" data-id="<?php echo esc_attr($item['id']); ?>" style="background:#10b981;border-color:#059669;">
                    <span class="dashicons dashicons-undo"></span> <?php esc_html_e('Restaurar Transacción', 'aura-suite'); ?>
                </button>
                <?php endif; ?>

                <?php if (current_user_can('manage_options')): ?>
                <button type="button" class="button permanent-delete-transaction aura-btn-dock" data-id="<?php echo esc_attr($item['id']); ?>" style="color:#dc2626;border-color:#fecaca;">
                    <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Eliminar Definitivamente', 'aura-suite'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Preparar items para la tabla
     */
    public function prepare_items() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aura_finance_transactions';
        $categories_table = $wpdb->prefix . 'aura_finance_categories';
        
        // Paginación
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        // Configurar columnas
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Construir WHERE
        $where = array('t.deleted_at IS NOT NULL');
        
        // Filtrar por permisos
        if (!current_user_can('aura_finance_view_all')) {
            $where[] = $wpdb->prepare('t.created_by = %d', get_current_user_id());
        }
        
        // Filtro por tipo
        if (!empty($_REQUEST['filter_type'])) {
            $where[] = $wpdb->prepare('t.transaction_type = %s', sanitize_text_field($_REQUEST['filter_type']));
        }
        
        // Filtro por estado
        if (!empty($_REQUEST['filter_status'])) {
            $where[] = $wpdb->prepare('t.status = %s', sanitize_text_field($_REQUEST['filter_status']));
        }
        
        // Búsqueda
        if (!empty($_REQUEST['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['s'])) . '%';
            $where[] = $wpdb->prepare('(t.description LIKE %s OR t.reference_number LIKE %s OR t.recipient_payer LIKE %s)', $search, $search, $search);
        }
        
        $where_sql = implode(' AND ', $where);
        
        // Ordenamiento
        $orderby = !empty($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'deleted_at';
        $order = !empty($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        
        // Contar total
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} t WHERE {$where_sql}");
        
        // Obtener items
        $offset = ($current_page - 1) * $per_page;
        
        $query = "SELECT t.*, c.name as category_name, c.color as category_color, c.icon as category_icon 
                  FROM {$table_name} t 
                  LEFT JOIN {$categories_table} c ON t.category_id = c.id 
                  WHERE {$where_sql} 
                  ORDER BY {$orderby} {$order} 
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
}
