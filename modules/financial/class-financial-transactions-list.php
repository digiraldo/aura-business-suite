<?php
/**
 * Clase para gestionar el listado de transacciones financieras
 * Extiende WP_List_Table para crear una tabla administrativa completa
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 2.1.0
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
 * Clase Aura_Financial_Transactions_List
 * 
 * Gestiona el listado de transacciones con filtros avanzados,
 * búsqueda, paginación y acciones contextuales según permisos
 */
class Aura_Financial_Transactions_List extends WP_List_Table {
    
    /**
     * Estadísticas de transacciones filtradas
     *
     * @var array
     */
    private $stats = array(
        'total_income' => 0,
        'total_expense' => 0,
        'balance' => 0,
        'count' => 0
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'transacción',
            'plural'   => 'transacciones',
            'ajax'     => true
        ));
    }
    
    /**
     * Inicializar la clase y hooks
     */
    public static function init() {
        // AJAX handlers para filtros y búsqueda
        add_action('wp_ajax_aura_filter_transactions', array(__CLASS__, 'ajax_filter_transactions'));
        add_action('wp_ajax_aura_search_transactions', array(__CLASS__, 'ajax_search_transactions'));
        add_action('wp_ajax_aura_bulk_action_transactions', array(__CLASS__, 'ajax_bulk_action'));
        add_action('wp_ajax_aura_quick_approve', array(__CLASS__, 'ajax_quick_approve'));
        add_action('wp_ajax_aura_quick_reject', array(__CLASS__, 'ajax_quick_reject'));
        add_action('wp_ajax_aura_save_filter_preset', array(__CLASS__, 'ajax_save_filter_preset'));
        add_action('wp_ajax_aura_load_filter_preset', array(__CLASS__, 'ajax_load_filter_preset'));
        
        // Enqueue scripts y styles
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue scripts y styles
     */
    public static function enqueue_scripts($hook) {
        // Solo cargar en la página de listado de transacciones
        if ('aura-suite_page_aura-financial-transactions' !== $hook) {
            return;
        }
        
        // jQuery UI Datepicker para rango de fechas
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css');
        
        // Select2 para dropdowns múltiples
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));
        
        // Scripts personalizados
        wp_enqueue_script(
            'aura-transactions-list',
            AURA_PLUGIN_URL . 'assets/js/transactions-list.js',
            array('jquery', 'jquery-ui-datepicker', 'select2'),
            AURA_VERSION,
            true
        );
        
        // Styles personalizados
        wp_enqueue_style(
            'aura-transactions-list',
            AURA_PLUGIN_URL . 'assets/css/transactions-list.css',
            array(),
            AURA_VERSION
        );
        
        // Localizar script
        wp_localize_script('aura-transactions-list', 'auraTransactionsList', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aura_transactions_list_nonce'),
            'messages' => array(
                'confirmBulkDelete' => __('¿Estás seguro de eliminar las transacciones seleccionadas?', 'aura-suite'),
                'confirmApprove' => __('¿Aprobar esta transacción?', 'aura-suite'),
                'confirmReject' => __('¿Rechazar esta transacción?', 'aura-suite'),
                'rejectReason' => __('Ingresa el motivo del rechazo:', 'aura-suite'),
                'filterSaved' => __('Filtro guardado exitosamente', 'aura-suite'),
                'filterLoaded' => __('Filtro cargado', 'aura-suite'),
            ),
            'userCan' => array(
                'view_all' => current_user_can('aura_finance_view_all'),
                'edit_all' => current_user_can('aura_finance_edit_all'),
                'delete_all' => current_user_can('aura_finance_delete_all'),
                'approve' => current_user_can('aura_finance_approve'),
            ),
        ));
    }
    
    /**
     * Definir columnas de la tabla
     */
    public function get_columns() {
        $columns = array(
            'cb'              => '<input type="checkbox" />',
            'id'              => __('#', 'aura-suite'),           // # + flecha tipo
            'status'          => __('Estado', 'aura-suite'),      // badge + aprobación inline
            'transaction_date'=> __('Fecha', 'aura-suite'),
            'category'        => __('Categoría', 'aura-suite'),   // badge con tooltip descripción
            'area'            => __('Área/Programa', 'aura-suite'),
            'amount'          => __('Monto', 'aura-suite'),
            'payment_method'  => __('Pago', 'aura-suite'),        // solo ícono
            'related_user'    => __('Vinculado', 'aura-suite'),
            'created_by'      => __('Crea', 'aura-suite'),        // avatar + nombre
            'actions'         => __('', 'aura-suite'),            // solo íconos
        );
        
        return $columns;
    }
    
    /**
     * Definir columnas ordenables
     */
    public function get_sortable_columns() {
        return array(
            'transaction_date' => array('transaction_date', true),
            'amount'           => array('amount', false),
            'status'           => array('status', false),
        );
    }
    
    /**
     * Checkbox para selección masiva
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="transaction_ids[]" value="%d" />',
            $item->id
        );
    }
    
    /**
     * Columna #ID — incluye flecha de tipo (↑ ingreso / ↓ egreso)
     */
    public function column_id( $item ) {
        $is_income  = ( $item->transaction_type === 'income' );
        $arrow      = $is_income ? '&#8593;' : '&#8595;'; // ↑ ↓
        $color      = $is_income ? '#27ae60' : '#e74c3c';
        $type_label = $is_income ? __('Ingreso', 'aura-suite') : __('Egreso', 'aura-suite');
        return sprintf(
            '<span class="aura-txn-id" style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">'
            . '<span style="color:%s;font-size:15px;line-height:1;" title="%s">%s</span>'
            . '<span style="font-family:monospace;font-size:12px;color:#555;font-weight:600;">#%d</span>'
            . '</span>',
            esc_attr( $color ),
            esc_attr( $type_label ),
            $arrow,
            $item->id
        );
    }

    /**
     * Columna de estado — incluye indicador de método de aprobación
     */
    public function column_status( $item ) {
        $status_map = array(
            'pending'  => array( 'label' => __('Pendiente', 'aura-suite'),  'color' => '#f39c12', 'dashicon' => 'dashicons-clock' ),
            'approved' => array( 'label' => __('Aprobado', 'aura-suite'), 'color' => '#27ae60', 'dashicon' => 'dashicons-yes-alt' ),
            'rejected' => array( 'label' => __('Rechazado', 'aura-suite'),'color' => '#e74c3c', 'dashicon' => 'dashicons-dismiss' ),
        );

        $s     = $status_map[ $item->status ] ?? $status_map['pending'];
        $title = $s['label'];

        // Si está rechazada: añadir motivo al tooltip
        if ( $item->status === 'rejected' && ! empty( $item->rejection_reason ) ) {
            $title .= ' — ' . $item->rejection_reason;
        }

        $badge = sprintf(
            '<span class="aura-status-badge" '
            . 'title="%s" '
            . 'style="display:inline-flex;align-items:center;gap:3px;background:%s;color:#fff;padding:3px 7px;border-radius:10px;font-size:11px;white-space:nowrap;cursor:default;">'
            . '<span class="dashicons %s" style="font-size:12px;width:12px;height:12px;margin-top:1px;"></span>%s'
            . '</span>',
            esc_attr( $title ),
            esc_attr( $s['color'] ),
            esc_attr( $s['dashicon'] ),
            esc_html( $s['label'] )
        );

        // Indicador de aprobación automática (solo transacciones aprobadas)
        if ( $item->status === 'approved' && ! empty( $item->approved_by ) ) {
            $is_auto = ( $item->approved_by == $item->created_by );
            if ( $is_auto ) {
                $threshold = (float) get_option('aura_finance_auto_approval_threshold', 0);
                $auto_tip  = $threshold > 0
                    ? sprintf( __('Auto-aprobada (monto $%s < umbral $%s)', 'aura-suite'),
                        number_format( $item->amount, 0, '.', ',' ),
                        number_format( $threshold, 0, '.', ',' ) )
                    : __('Auto-aprobada', 'aura-suite');
                $badge .= sprintf(
                    '<span class="dashicons dashicons-superhero" style="color:#10b981;font-size:13px;width:13px;height:13px;vertical-align:middle;cursor:help;margin-left:2px;" title="%s"></span>',
                    esc_attr( $auto_tip )
                );
            } else {
                $approver      = get_userdata( $item->approved_by );
                $approver_name = $approver ? $approver->display_name : __('N/D', 'aura-suite');
                $badge .= sprintf(
                    '<span class="dashicons dashicons-admin-users" style="color:#3b82f6;font-size:13px;width:13px;height:13px;vertical-align:middle;cursor:help;margin-left:2px;" title="%s"></span>',
                    esc_attr( sprintf( __('Aprobada por: %s', 'aura-suite'), $approver_name ) )
                );
            }
        }

        return $badge;
    }
    
    /**
     * @deprecated Fusionada dentro de column_status()
     */
    public function column_approval_method($item) {
        // Solo mostrar para transacciones aprobadas
        if ($item->status !== 'approved') {
            return '<span style="color: #999;">—</span>';
        }
        
        // No mostrar si no hay información de aprobación
        if (empty($item->approved_by) || empty($item->created_by)) {
            return '<span style="color: #999;">—</span>';
        }
        
        $is_auto_approved = ($item->approved_by == $item->created_by);
        
        if ($is_auto_approved) {
            // Auto-aprobada
            $badge = sprintf(
                '<span class="aura-approval-badge auto" style="background-color: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 3px; font-size: 11px; display: inline-block;">
                    <span class="dashicons dashicons-yes-alt" style="font-size: 11px; width: 11px; height: 11px; vertical-align: text-top;"></span> %s
                </span>',
                __('Automática', 'aura-suite')
            );
            
            // Agregar tooltip con información del umbral
            $threshold = (float) get_option('aura_finance_auto_approval_threshold', 0);
            if ($threshold > 0) {
                $tooltip = sprintf(
                    __('Auto-aprobada (monto: $%s < umbral: $%s)', 'aura-suite'),
                    number_format($item->amount, 2),
                    number_format($threshold, 2)
                );
                $badge .= sprintf(
                    ' <span class="dashicons dashicons-info-outline" style="color: #10b981; cursor: help; font-size: 14px;" title="%s"></span>',
                    esc_attr($tooltip)
                );
            }
        } else {
            // Aprobación manual
            $approver = get_userdata($item->approved_by);
            $approver_name = $approver ? $approver->display_name : __('Desconocido', 'aura-suite');
            
            $badge = sprintf(
                '<span class="aura-approval-badge manual" style="background-color: #dbeafe; color: #1e3a8a; padding: 4px 8px; border-radius: 3px; font-size: 11px; display: inline-block;">
                    <span class="dashicons dashicons-admin-users" style="font-size: 11px; width: 11px; height: 11px; vertical-align: text-top;"></span> %s
                </span>',
                __('Manual', 'aura-suite')
            );
            
            // Agregar tooltip con nombre del aprobador
            $badge .= sprintf(
                ' <span class="dashicons dashicons-info-outline" style="color: #3b82f6; cursor: help; font-size: 14px;" title="%s"></span>',
                esc_attr(sprintf(__('Aprobada por: %s', 'aura-suite'), $approver_name))
            );
        }
        
        return $badge;
    }
    
    /**
     * Columna de fecha
     */
    public function column_transaction_date($item) {
        $date = new DateTime($item->transaction_date);
        return $date->format('d/m/Y');
    }
    
    /**
     * @deprecated El tipo ahora va dentro de column_id()
     */
    public function column_type($item) {
        return '';
    }
    
    /**
     * Columna de categoría (usa datos precargados del JOIN en prepare_items)
     */
    public function column_category($item) {
        $description = ! empty( $item->description ) ? esc_attr( $item->description ) : '';
        $tooltip_attr = $description ? ' title="' . $description . '" style="background-color: ' . esc_attr( $item->category_color ?: '#8c8f94' ) . '; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; cursor: help; white-space: nowrap;"' : ' style="background-color: ' . esc_attr( $item->category_color ?: '#8c8f94' ) . '; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; white-space: nowrap;"';
        if ( ! empty( $item->category_name ) ) {
            $icon = $description ? ' <span style="font-size:10px;opacity:0.85;">&#128172;</span>' : '';
            return sprintf(
                '<span class="aura-category-badge"%s>%s%s</span>',
                $tooltip_attr,
                esc_html( $item->category_name ),
                $icon
            );
        }
        if ( $description ) {
            return '<em title="' . $description . '" style="cursor:help;">' . __( 'Sin categoría', 'aura-suite' ) . ' &#128172;</em>';
        }
        return '<em>' . __( 'Sin categoría', 'aura-suite' ) . '</em>';
    }

    /**
     * Columna de área / programa (usa datos precargados del JOIN en prepare_items)
     */
    public function column_area($item) {
        if ( ! empty( $item->area_name ) ) {
            $color = $item->area_color ?: '#8c8f94';
            $icon  = $item->area_icon  ?: 'dashicons-category';
            return sprintf(
                '<span class="aura-area-badge" title="%s" style="--area-color:%s;">'
                . '<span class="dashicons %s" style="color:%s;font-size:14px;width:14px;height:14px;line-height:1;"></span>'
                . '<span class="aura-area-badge__name">%s</span>'
                . '</span>',
                esc_attr( $item->area_name ),
                esc_attr( $color ),
                esc_attr( $icon ),
                esc_attr( $color ),
                esc_html( $item->area_name )
            );
        }
        return '<span style="color:#aaa;font-style:italic;">' . __( 'General', 'aura-suite' ) . '</span>';
    }
    
    /**
     * Columna de descripción
     */
    public function column_description($item) {
        $description = esc_html($item->description);
        
        if (strlen($description) > 50) {
            $truncated = substr($description, 0, 50) . '...';
            return sprintf(
                '<span style="white-space:normal;word-break:break-word;" title="%s">%s</span>',
                $description,
                $truncated
            );
        }
        
        return '<span style="white-space:normal;word-break:break-word;">' . $description . '</span>';
    }
    
    /**
     * Columna de monto
     */
    public function column_amount($item) {
        $color = $item->transaction_type === 'income' ? '#27ae60' : '#e74c3c';
        $sign = $item->transaction_type === 'income' ? '+' : '-';
        
        return sprintf(
            '<strong style="color: %s;">%s$%s</strong>',
            $color,
            $sign,
            number_format($item->amount, 2, '.', ',')
        );
    }
    
    /**
     * Obtener información de método de pago (icono, color y texto traducido)
     * 
     * @param string $payment_method Método de pago en inglés o español
     * @return array Array con 'icon', 'color' y 'label'
     */
    public static function get_payment_method_info( $payment_method ) {
        // Mapeo de métodos de pago: inglés => español
        $translations = array(
            'cash'     => 'Efectivo',
            'transfer' => 'Transferencia',
            'check'    => 'Cheque',
            'card'     => 'Tarjeta',
            'other'    => 'Otro'
        );
        
        // Si viene en inglés, traducir a español
        $method_spanish = isset( $translations[ $payment_method ] ) 
            ? $translations[ $payment_method ] 
            : $payment_method;
        
        // Mapeo de iconos y colores
        $map = array(
            'Efectivo'       => array( 'icon' => 'dashicons-money-alt',  'color' => '#27ae60' ),
            'Transferencia'  => array( 'icon' => 'dashicons-bank',       'color' => '#3b82f6' ),
            'Cheque'         => array( 'icon' => 'dashicons-media-text', 'color' => '#6366f1' ),
            'Tarjeta'        => array( 'icon' => 'dashicons-id-alt',     'color' => '#8b5cf6' ),
            'Otro'           => array( 'icon' => 'dashicons-money',      'color' => '#8c8f94' ),
        );
        
        $info = $map[ $method_spanish ] ?? array( 'icon' => 'dashicons-money-alt', 'color' => '#8c8f94' );
        $info['label'] = $method_spanish;
        
        return $info;
    }
    
    /**
     * Columna de método de pago — solo ícono con tooltip
     */
    public function column_payment_method( $item ) {
        if ( empty( $item->payment_method ) ) {
            return '<span style="color:#ccc;">—</span>';
        }

        $info = self::get_payment_method_info( $item->payment_method );

        return sprintf(
            '<span class="dashicons %s" title="%s" style="color:%s;font-size:16px;width:16px;height:16px;cursor:default;"></span>',
            esc_attr( $info['icon'] ),
            esc_attr( $info['label'] ),
            esc_attr( $info['color'] )
        );
    }
    
    /**
     * Columna: Usuario Vinculado — compacto con avatar + tooltip concepto
     */
    public function column_related_user( $item ) {
        if ( ! empty( $item->related_user_id ) ) {
            $user = get_userdata( $item->related_user_id );
            if ( $user ) {
                $concepts = array(
                    'payment_to_user'       => __('Pago a usuario', 'aura-suite'),
                    'charge_to_user'        => __('Cobro a usuario', 'aura-suite'),
                    'salary'                => __('Nómina/Sueldo', 'aura-suite'),
                    'scholarship'           => __('Beca', 'aura-suite'),
                    'loan_payment'          => __('Préstamo', 'aura-suite'),
                    'refund'                => __('Reembolso', 'aura-suite'),
                    'expense_reimbursement' => __('Reemb. de gastos', 'aura-suite'),
                );
                $concept_label = ! empty( $item->related_user_concept )
                    ? ( $concepts[ $item->related_user_concept ] ?? $item->related_user_concept )
                    : '';

                // Rol del usuario
                global $wp_roles;
                $roles_list = array();
                foreach ( $user->roles as $role ) {
                    $roles_list[] = isset( $wp_roles->roles[ $role ] )
                        ? translate_user_role( $wp_roles->roles[ $role ]['name'] )
                        : $role;
                }
                $role_str = implode( ', ', $roles_list );

                // Tooltip enriquecido: nombre · email · rol · concepto
                $tooltip_parts = array( $user->display_name );
                if ( $user->user_email ) { $tooltip_parts[] = $user->user_email; }
                if ( $role_str )         { $tooltip_parts[] = $role_str; }
                if ( $concept_label )    { $tooltip_parts[] = $concept_label; }
                $tooltip = implode( ' · ', $tooltip_parts );

                $avatar  = get_avatar_url( $item->related_user_id, array( 'size' => 20 ) );
                $initial = mb_strtoupper( mb_substr( $user->display_name, 0, 1 ) );
                return sprintf(
                    '<span class="aura-user-compact" title="%s" style="display:inline-flex;align-items:center;gap:4px;">'
                    . '<img src="%s" width="20" height="20" style="border-radius:50%%;flex-shrink:0;">'
                    . '<span class="aura-user-initial">%s</span></span>',
                    esc_attr( $tooltip ),
                    esc_url( $avatar ),
                    esc_html( $initial )
                );
            }
        }
        if ( ! empty( $item->recipient_payer ) ) {
            $initial = mb_strtoupper( mb_substr( $item->recipient_payer, 0, 1 ) );
            return '<span style="color:#646970;font-size:12px;" title="' . esc_attr( $item->recipient_payer ) . '">' . esc_html( $initial ) . '</span>';
        }
        return '<span style="color:#ccc;">—</span>';
    }

    /**
     * Columna de creado por — avatar + nombre compacto con tooltip
     */
    public function column_created_by( $item ) {
        $user = get_userdata( $item->created_by );
        if ( $user ) {
            // Rol del usuario
            global $wp_roles;
            $roles_list = array();
            foreach ( $user->roles as $role ) {
                $roles_list[] = isset( $wp_roles->roles[ $role ] )
                    ? translate_user_role( $wp_roles->roles[ $role ]['name'] )
                    : $role;
            }
            $role_str = implode( ', ', $roles_list );

            // Tooltip enriquecido: nombre · email · rol
            $tooltip_parts = array( $user->display_name );
            if ( $user->user_email ) { $tooltip_parts[] = $user->user_email; }
            if ( $role_str )         { $tooltip_parts[] = $role_str; }
            $tooltip = implode( ' · ', $tooltip_parts );

            $avatar  = get_avatar_url( $item->created_by, array( 'size' => 20 ) );
            $initial = mb_strtoupper( mb_substr( $user->display_name, 0, 1 ) );
            return sprintf(
                '<span class="aura-user-compact" title="%s" style="display:inline-flex;align-items:center;gap:4px;">'
                . '<img src="%s" width="20" height="20" style="border-radius:50%%;flex-shrink:0;">'
                . '<span class="aura-user-initial">%s</span></span>',
                esc_attr( $tooltip ),
                esc_url( $avatar ),
                esc_html( $initial )
            );
        }
        return '<span style="color:#ccc;">?</span>';
    }
    
    /**
     * Columna de acciones — íconos dashicons con tooltip
     */
    public function column_actions( $item ) {
        $current_uid = get_current_user_id();
        $is_owner    = ( (int) $item->created_by === $current_uid );
        $btns        = array();

        // ── Ver detalle ──────────────────────────────────────────
        if ( current_user_can('aura_finance_view_all') ||
             ( current_user_can('aura_finance_view_own') && $is_owner ) ) {
            $btns[] = sprintf(
                '<button type="button" class="aura-icon-btn view-transaction" data-transaction-id="%d" title="%s" aria-label="%s">'
                . '<span class="dashicons dashicons-visibility"></span></button>',
                $item->id,
                esc_attr__('Ver detalles', 'aura-suite'),
                esc_attr__('Ver detalles', 'aura-suite')
            );
        }

        // ── Editar ───────────────────────────────────────────────
        if ( current_user_can('aura_finance_edit_all') ||
             ( current_user_can('aura_finance_edit_own') && $is_owner && in_array( $item->status, array('pending','rejected') ) ) ) {
            $is_rejected  = ( $item->status === 'rejected' );
            $edit_title   = $is_rejected
                ? sprintf( __('Corregir y reenviar. Motivo: %s', 'aura-suite'), $item->rejection_reason ?? __('N/D', 'aura-suite') )
                : __('Editar transacción', 'aura-suite');
            $edit_icon    = $is_rejected ? 'dashicons-update' : 'dashicons-edit';
            $edit_color   = $is_rejected ? '#f59e0b' : '#3b82f6';
            $btns[] = sprintf(
                '<a href="%s" class="aura-icon-btn" title="%s" aria-label="%s" style="color:%s;">'
                . '<span class="dashicons %s"></span></a>',
                esc_url( admin_url('admin.php?page=aura-financial-edit-transaction&id=' . $item->id) ),
                esc_attr( $edit_title ),
                esc_attr( $edit_title ),
                esc_attr( $edit_color ),
                esc_attr( $edit_icon )
            );
        }

        // ── Aprobar ──────────────────────────────────────────────
        if ( current_user_can('aura_finance_approve') && $item->status === 'pending' ) {
            $btns[] = sprintf(
                '<a href="#" class="aura-icon-btn aura-quick-approve" data-id="%d" title="%s" aria-label="%s" style="color:#27ae60;">'
                . '<span class="dashicons dashicons-yes-alt"></span></a>',
                $item->id,
                esc_attr__('Aprobar', 'aura-suite'),
                esc_attr__('Aprobar', 'aura-suite')
            );

            // ── Rechazar ─────────────────────────────────────────
            $btns[] = sprintf(
                '<a href="#" class="aura-icon-btn aura-quick-reject" data-id="%d" title="%s" aria-label="%s" style="color:#e74c3c;">'
                . '<span class="dashicons dashicons-dismiss"></span></a>',
                $item->id,
                esc_attr__('Rechazar', 'aura-suite'),
                esc_attr__('Rechazar', 'aura-suite')
            );
        }

        // ── Eliminar ─────────────────────────────────────────────
        if ( current_user_can('aura_finance_delete_all') ||
             ( current_user_can('aura_finance_delete_own') && $is_owner ) ) {
            $btns[] = sprintf(
                '<a href="#" class="aura-icon-btn aura-delete-transaction" data-id="%d" title="%s" aria-label="%s" style="color:#e74c3c;">'
                . '<span class="dashicons dashicons-trash"></span></a>',
                $item->id,
                esc_attr__('Eliminar', 'aura-suite'),
                esc_attr__('Eliminar', 'aura-suite')
            );
        }

        return '<div class="aura-row-actions-icons">' . implode( '', $btns ) . '</div>';
    }
    
    /**
     * Acciones en bloque
     */
    public function get_bulk_actions() {
        $actions = array();
        
        if (current_user_can('aura_finance_approve')) {
            $actions['bulk_approve'] = __('Aprobar seleccionadas', 'aura-suite');
        }
        
        if (current_user_can('aura_finance_delete_all')) {
            $actions['bulk_delete'] = __('Eliminar seleccionadas', 'aura-suite');
        }
        
        $actions['bulk_export_csv'] = __('Exportar a CSV', 'aura-suite');
        $actions['bulk_export_pdf'] = __('Exportar a PDF', 'aura-suite');
        
        return $actions;
    }
    
    /**
     * Preparar items para la tabla
     */
    public function prepare_items() {
        global $wpdb;
        
        $per_page = $this->get_items_per_page('transactions_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Construir WHERE clause con filtros
        // IMPORTANTE: Calificar todos los campos con t. para evitar ambigüedad en LEFT JOINs
        $where_clauses = array('t.deleted_at IS NULL');
        $where_values = array();
        
        // Filtro por tipo
        if (!empty($_REQUEST['filter_type'])) {
            $where_clauses[] = 't.transaction_type = %s';
            $where_values[] = sanitize_text_field($_REQUEST['filter_type']);
        }
        
        // Filtro por estado
        if (!empty($_REQUEST['filter_status'])) {
            $where_clauses[] = 't.status = %s';
            $where_values[] = sanitize_text_field($_REQUEST['filter_status']);
        }
        
        // Filtro por categoría
        if (!empty($_REQUEST['filter_category'])) {
            $where_clauses[] = 't.category_id = %d';
            $where_values[] = intval($_REQUEST['filter_category']);
        }
        
        // Filtro por rango de fechas
        if (!empty($_REQUEST['filter_date_from'])) {
            $where_clauses[] = 't.transaction_date >= %s';
            $where_values[] = sanitize_text_field($_REQUEST['filter_date_from']);
        }
        if (!empty($_REQUEST['filter_date_to'])) {
            $where_clauses[] = 't.transaction_date <= %s';
            $where_values[] = sanitize_text_field($_REQUEST['filter_date_to']);
        }
        
        // Filtro por rango de monto
        if (!empty($_REQUEST['filter_amount_min'])) {
            $where_clauses[] = 't.amount >= %f';
            $where_values[] = floatval($_REQUEST['filter_amount_min']);
        }
        if (!empty($_REQUEST['filter_amount_max'])) {
            $where_clauses[] = 't.amount <= %f';
            $where_values[] = floatval($_REQUEST['filter_amount_max']);
        }
        
        // Filtro por creador (manejo de permisos)
        if (!empty($_REQUEST['filter_user']) && current_user_can('aura_finance_view_all')) {
            // Admin/Contador pueden filtrar por cualquier usuario
            $where_clauses[] = 't.created_by = %d';
            $where_values[] = intval($_REQUEST['filter_user']);
        } elseif (current_user_can('aura_finance_view_own') && !current_user_can('aura_finance_view_all')) {
            // Usuario con permiso view_own solo ve sus propias transacciones
            $where_clauses[] = 't.created_by = %d';
            $where_values[] = get_current_user_id();
        }

        // Fase 6, Item 6.1: Filtro por usuario vinculado (related_user_id)
        if ( ! empty( $_REQUEST['filter_related_user'] )
             && ( current_user_can( 'aura_finance_view_all' ) || current_user_can( 'aura_finance_user_ledger' ) ) ) {
            $where_clauses[] = 't.related_user_id = %d';
            $where_values[]  = intval( $_REQUEST['filter_related_user'] );
        }

        // Fase 8.2: Filtro por área
        // Prioridad 1: Usuario responsable de área (solo ve transacciones de su área)
        if ( current_user_can( 'aura_areas_view_own' )
             && ! current_user_can( 'aura_areas_view_all' )
             && ! current_user_can( 'manage_options' )
             && ! current_user_can( 'aura_finance_view_own' ) ) {
            // Forzar el área del responsable (solo si NO tiene view_own que tiene mayor prioridad)
            $user_area_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aura_areas WHERE responsible_user_id = %d AND status = 'active' LIMIT 1",
                get_current_user_id()
            ) );
            if ( $user_area_id ) {
                $where_clauses[] = 't.area_id = %d';
                $where_values[]  = $user_area_id;
            }
        } elseif ( ! empty( $_REQUEST['filter_area'] ) && current_user_can( 'aura_finance_view_all' ) ) {
            // Solo admins pueden filtrar por área específica
            $where_clauses[] = 't.area_id = %d';
            $where_values[]  = intval( $_REQUEST['filter_area'] );
        }
        
        // Búsqueda global
        if (!empty($_REQUEST['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['s'])) . '%';
            $where_clauses[] = '(t.description LIKE %s OR t.notes LIKE %s OR t.reference_number LIKE %s OR t.recipient_payer LIKE %s)';
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
        }
        
        // Construir WHERE final
        $where_sql = implode(' AND ', $where_clauses);
        if (!empty($where_values)) {
            $where_sql = $wpdb->prepare($where_sql, $where_values);
        }
        
        // Ordenamiento
        $orderby = !empty($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'transaction_date';
        $order = !empty($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        
        $table_name = $wpdb->prefix . 'aura_finance_transactions';
        $cat_table   = $wpdb->prefix . 'aura_finance_categories';
        $area_table  = $wpdb->prefix . 'aura_areas';

        // Obtener total de items
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name t WHERE $where_sql");

        // Obtener items con JOIN a categorías y áreas (evita N+1 queries)
        $this->items = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.*,
                    c.name  AS category_name,
                    c.color AS category_color,
                    c.icon  AS category_icon,
                    a.name  AS area_name,
                    a.color AS area_color,
                    a.icon  AS area_icon
             FROM $table_name t
             LEFT JOIN $cat_table  c ON c.id = t.category_id
             LEFT JOIN $area_table a ON a.id = t.area_id
             WHERE $where_sql
             ORDER BY t.$orderby $order
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ) );
        
        // Calcular estadísticas
        $this->calculate_stats($where_sql, $table_name);
        
        // Configurar paginación
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        // Headers de columnas
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array($columns, $hidden, $sortable);
    }
    
    /**
     * Calcular estadísticas de transacciones filtradas
     */
    private function calculate_stats($where_sql, $table_name = '') {
        global $wpdb;

        if ( empty( $table_name ) ) {
            $table_name = $wpdb->prefix . 'aura_finance_transactions';
        }
        
        // Solo se contabilizan transacciones aprobadas en los totales
        // (pending y rejected no afectan la situación financiera real).
        $stats = $wpdb->get_row("
            SELECT 
                SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as total_expense,
                COUNT(*) as count
            FROM $table_name t
            WHERE $where_sql
              AND t.status NOT IN ('pending', 'rejected')
        ");
        
        if ($stats) {
            $this->stats['total_income'] = floatval($stats->total_income);
            $this->stats['total_expense'] = floatval($stats->total_expense);
            $this->stats['balance'] = $this->stats['total_income'] - $this->stats['total_expense'];
            $this->stats['count'] = intval($stats->count);
        }
    }
    
    /**
     * Obtener estadísticas
     */
    public function get_stats() {
        return $this->stats;
    }
    
    /**
     * AJAX: Filtrar transacciones
     */
    public static function ajax_filter_transactions() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        // Los filtros se pasan como parámetros GET
        // Redirigir a la página con los filtros
        $filters = array();
        $allowed_filters = array(
            'filter_type', 'filter_status', 'filter_category',
            'filter_date_from', 'filter_date_to',
            'filter_amount_min', 'filter_amount_max',
            'filter_user', 'filter_payment_method',
            'filter_related_user', 'filter_area',
        );
        
        foreach ($allowed_filters as $filter) {
            if (!empty($_POST[$filter])) {
                $filters[$filter] = sanitize_text_field($_POST[$filter]);
            }
        }
        
        wp_send_json_success(array(
            'redirect_url' => add_query_arg($filters, admin_url('admin.php?page=aura-financial-transactions'))
        ));
    }
    
    /**
     * AJAX: Búsqueda en tiempo real
     */
    public static function ajax_search_transactions() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        global $wpdb;
        $search_term = sanitize_text_field($_POST['search'] ?? '');
        
        if (empty($search_term)) {
            wp_send_json_success(array('results' => array()));
        }
        
        $table_name = $wpdb->prefix . 'aura_finance_transactions';
        $search = '%' . $wpdb->esc_like($search_term) . '%';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT id, transaction_type, description, amount, transaction_date
            FROM $table_name
            WHERE deleted_at IS NULL
            AND (description LIKE %s OR notes LIKE %s OR reference_number LIKE %s)
            ORDER BY transaction_date DESC
            LIMIT 10
        ", $search, $search, $search));
        
        wp_send_json_success(array('results' => $results));
    }
    
    /**
     * AJAX: Aprobar rápidamente
     */
    public static function ajax_quick_approve() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        if (!current_user_can('aura_finance_approve')) {
            wp_send_json_error(array('message' => __('No tienes permisos', 'aura-suite')));
        }
        
        $transaction_id = intval($_POST['transaction_id'] ?? 0);
        
        if ($transaction_id <= 0) {
            wp_send_json_error(array('message' => __('ID inválido', 'aura-suite')));
        }
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'aura_finance_transactions',
            array(
                'status' => 'approved',
                'approved_by' => get_current_user_id(),
                'approved_at' => current_time('mysql')
            ),
            array('id' => $transaction_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Error al aprobar', 'aura-suite')));
        }
        
        do_action('aura_finance_transaction_approved', $transaction_id);
        
        wp_send_json_success(array('message' => __('Transacción aprobada', 'aura-suite')));
    }
    
    /**
     * AJAX: Rechazar rápidamente
     */
    public static function ajax_quick_reject() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        if (!current_user_can('aura_finance_approve')) {
            wp_send_json_error(array('message' => __('No tienes permisos', 'aura-suite')));
        }
        
        $transaction_id = intval($_POST['transaction_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        
        if ($transaction_id <= 0) {
            wp_send_json_error(array('message' => __('ID inválido', 'aura-suite')));
        }
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'aura_finance_transactions',
            array(
                'status' => 'rejected',
                'rejection_reason' => $reason
            ),
            array('id' => $transaction_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Error al rechazar', 'aura-suite')));
        }
        
        do_action('aura_finance_transaction_rejected', $transaction_id, $reason);
        
        wp_send_json_success(array('message' => __('Transacción rechazada', 'aura-suite')));
    }
    
    /**
     * AJAX: Acciones masivas
     */
    public static function ajax_bulk_action() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['action_type'] ?? '');
        $transaction_ids = array_map('intval', $_POST['transaction_ids'] ?? array());
        
        if (empty($transaction_ids)) {
            wp_send_json_error(array('message' => __('No se seleccionaron transacciones', 'aura-suite')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aura_finance_transactions';
        
        switch ($action) {
            case 'bulk_approve':
                if (!current_user_can('aura_finance_approve')) {
                    wp_send_json_error(array('message' => __('No tienes permisos', 'aura-suite')));
                }
                
                $ids_placeholders = implode(',', array_fill(0, count($transaction_ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table_name SET status = 'approved', approved_by = %d, approved_at = %s WHERE id IN ($ids_placeholders)",
                    get_current_user_id(),
                    current_time('mysql'),
                    ...$transaction_ids
                ));
                
                wp_send_json_success(array('message' => __('Transacciones aprobadas', 'aura-suite')));
                break;
                
            case 'bulk_delete':
                $current_user_id = get_current_user_id();
                $can_delete_all = current_user_can('aura_finance_delete_all');
                $can_delete_own = current_user_can('aura_finance_delete_own');
                
                if (!$can_delete_all && !$can_delete_own) {
                    wp_send_json_error(array('message' => __('No tienes permisos para eliminar transacciones', 'aura-suite')));
                }
                
                // Si solo tiene permiso para eliminar propias, filtrar solo las suyas
                if (!$can_delete_all && $can_delete_own) {
                    // Verificar que todas las transacciones sean del usuario actual
                    $ids_placeholders = implode(',', array_fill(0, count($transaction_ids), '%d'));
                    
                    // Preparar argumentos: IDs + current_user_id
                    $prepare_args = array_merge($transaction_ids, array($current_user_id));
                    
                    $owned_transactions = $wpdb->get_col($wpdb->prepare(
                        "SELECT id FROM $table_name WHERE id IN ($ids_placeholders) AND created_by = %d",
                        ...$prepare_args
                    ));
                    
                    if (count($owned_transactions) !== count($transaction_ids)) {
                        wp_send_json_error(array('message' => __('Solo puedes eliminar tus propias transacciones', 'aura-suite')));
                    }
                    
                    $transaction_ids = $owned_transactions;
                }
                
                if (empty($transaction_ids)) {
                    wp_send_json_error(array('message' => __('No hay transacciones para eliminar', 'aura-suite')));
                }
                
                $ids_placeholders = implode(',', array_fill(0, count($transaction_ids), '%d'));
                $affected = $wpdb->query($wpdb->prepare(
                    "UPDATE $table_name SET deleted_at = %s, deleted_by = %d WHERE id IN ($ids_placeholders)",
                    current_time('mysql'),
                    $current_user_id,
                    ...$transaction_ids
                ));
                
                // Registrar en historial quién eliminó cada transacción
                if ($affected) {
                    $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
                    $deleted_by = $current_user_id;
                    $deleted_at = current_time('mysql');
                    foreach ($transaction_ids as $tid) {
                        $wpdb->insert(
                            $history_table,
                            array(
                                'transaction_id' => $tid,
                                'field_changed'  => 'status_deletion',
                                'old_value'      => 'active',
                                'new_value'      => 'soft_delete',
                                'change_reason'  => __('Enviado a papelera', 'aura-suite'),
                                'changed_by'     => $deleted_by,
                                'changed_at'     => $deleted_at,
                            ),
                            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
                        );
                    }
                }
                
                wp_send_json_success(array(
                    'message' => sprintf(
                        _n('%d transacción eliminada', '%d transacciones eliminadas', $affected, 'aura-suite'),
                        $affected
                    )
                ));
                break;
                
            default:
                wp_send_json_error(array('message' => __('Acción no válida', 'aura-suite')));
        }
    }
    
    /**
     * AJAX: Guardar preset de filtros
     */
    public static function ajax_save_filter_preset() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        $preset_name = sanitize_text_field($_POST['preset_name'] ?? '');
        $filters = $_POST['filters'] ?? array();
        
        if (empty($preset_name)) {
            wp_send_json_error(array('message' => __('Nombre requerido', 'aura-suite')));
        }
        
        $user_presets = get_user_meta(get_current_user_id(), 'aura_finance_filter_presets', true);
        if (!is_array($user_presets)) {
            $user_presets = array();
        }
        
        $user_presets[$preset_name] = $filters;
        update_user_meta(get_current_user_id(), 'aura_finance_filter_presets', $user_presets);
        
        wp_send_json_success(array('message' => __('Filtro guardado', 'aura-suite')));
    }
    
    /**
     * AJAX: Cargar preset de filtros
     */
    public static function ajax_load_filter_preset() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        $preset_name = sanitize_text_field($_POST['preset_name'] ?? '');
        
        $user_presets = get_user_meta(get_current_user_id(), 'aura_finance_filter_presets', true);
        
        if (!is_array($user_presets) || !isset($user_presets[$preset_name])) {
            wp_send_json_error(array('message' => __('Filtro no encontrado', 'aura-suite')));
        }
        
        wp_send_json_success(array('filters' => $user_presets[$preset_name]));
    }
}

// Inicializar
Aura_Financial_Transactions_List::init();
