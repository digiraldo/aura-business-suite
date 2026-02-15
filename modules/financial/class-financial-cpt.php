<?php
/**
 * Custom Post Type para Transacciones Financieras
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar CPT de transacciones financieras
 */
class Aura_Financial_CPT {
    
    /**
     * Inicializar el CPT
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('init', array(__CLASS__, 'register_taxonomies'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post_aura_transaction', array(__CLASS__, 'save_transaction_meta'), 10, 2);
        add_action('transition_post_status', array(__CLASS__, 'handle_status_transition'), 10, 3);
        add_filter('manage_aura_transaction_posts_columns', array(__CLASS__, 'custom_columns'));
        add_action('manage_aura_transaction_posts_custom_column', array(__CLASS__, 'custom_column_content'), 10, 2);
        add_action('admin_menu', array(__CLASS__, 'add_menu_pages'));
    }
    
    /**
     * Registrar Custom Post Type
     */
    public static function register_post_type() {
        $labels = array(
            'name'               => __('Transacciones', 'aura-suite'),
            'singular_name'      => __('Transacción', 'aura-suite'),
            'add_new'            => __('Agregar Nueva', 'aura-suite'),
            'add_new_item'       => __('Agregar Nueva Transacción', 'aura-suite'),
            'edit_item'          => __('Editar Transacción', 'aura-suite'),
            'new_item'           => __('Nueva Transacción', 'aura-suite'),
            'view_item'          => __('Ver Transacción', 'aura-suite'),
            'search_items'       => __('Buscar Transacciones', 'aura-suite'),
            'not_found'          => __('No se encontraron transacciones', 'aura-suite'),
            'not_found_in_trash' => __('No hay transacciones en la papelera', 'aura-suite'),
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'aura-suite',
            'show_in_rest'        => true,
            'capability_type'     => 'aura_transaction',
            'map_meta_cap'        => true,
            'hierarchical'        => false,
            'supports'            => array('title', 'editor', 'author', 'comments'),
            'has_archive'         => false,
            'rewrite'             => false,
            'menu_icon'           => 'dashicons-money-alt',
        );
        
        register_post_type('aura_transaction', $args);
        
        // Agregar capabilities al rol administrator
        $admin = get_role('administrator');
        if ($admin) {
            $caps = array(
                'edit_aura_transaction',
                'read_aura_transaction',
                'delete_aura_transaction',
                'edit_aura_transactions',
                'edit_others_aura_transactions',
                'publish_aura_transactions',
                'read_private_aura_transactions',
                'delete_aura_transactions',
                'delete_private_aura_transactions',
                'delete_published_aura_transactions',
                'delete_others_aura_transactions',
                'edit_private_aura_transactions',
                'edit_published_aura_transactions',
            );
            foreach ($caps as $cap) {
                $admin->add_cap($cap);
            }
        }
    }
    
    /**
     * Registrar taxonomías
     */
    public static function register_taxonomies() {
        // Taxonomía: Tipo de Transacción
        register_taxonomy('aura_transaction_type', 'aura_transaction', array(
            'labels' => array(
                'name'          => __('Tipos de Transacción', 'aura-suite'),
                'singular_name' => __('Tipo', 'aura-suite'),
            ),
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => false,
        ));
        
        // Taxonomía: Categoría
        register_taxonomy('aura_transaction_category', 'aura_transaction', array(
            'labels' => array(
                'name'          => __('Categorías', 'aura-suite'),
                'singular_name' => __('Categoría', 'aura-suite'),
            ),
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => false,
        ));
        
        // Crear términos por defecto
        self::create_default_terms();
    }
    
    /**
     * Crear términos por defecto
     */
    private static function create_default_terms() {
        // Tipos de transacción
        $types = array(
            'income'  => __('Ingreso', 'aura-suite'),
            'expense' => __('Egreso', 'aura-suite'),
        );
        
        foreach ($types as $slug => $name) {
            if (!term_exists($slug, 'aura_transaction_type')) {
                wp_insert_term($name, 'aura_transaction_type', array('slug' => $slug));
            }
        }
        
        // Categorías
        $categories = array(
            'general'     => __('Gastos Generales', 'aura-suite'),
            'scholarships' => __('Becas', 'aura-suite'),
            'donations'   => __('Donaciones', 'aura-suite'),
            'sales'       => __('Ventas', 'aura-suite'),
            'services'    => __('Servicios', 'aura-suite'),
            'other'       => __('Otros', 'aura-suite'),
        );
        
        foreach ($categories as $slug => $name) {
            if (!term_exists($slug, 'aura_transaction_category')) {
                wp_insert_term($name, 'aura_transaction_category', array('slug' => $slug));
            }
        }
    }
    
    /**
     * Agregar meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'aura_transaction_details',
            __('Detalles de la Transacción', 'aura-suite'),
            array(__CLASS__, 'render_transaction_details_metabox'),
            'aura_transaction',
            'normal',
            'high'
        );
        
        add_meta_box(
            'aura_transaction_status',
            __('Estado de Aprobación', 'aura-suite'),
            array(__CLASS__, 'render_status_metabox'),
            'aura_transaction',
            'side',
            'high'
        );
    }
    
    /**
     * Renderizar metabox de detalles
     */
    public static function render_transaction_details_metabox($post) {
        wp_nonce_field('aura_transaction_meta', 'aura_transaction_meta_nonce');
        
        $amount = get_post_meta($post->ID, '_aura_transaction_amount', true);
        $date = get_post_meta($post->ID, '_aura_transaction_date', true);
        $attachment = get_post_meta($post->ID, '_aura_transaction_attachment', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="aura_amount"><?php _e('Monto ($)', 'aura-suite'); ?></label></th>
                <td>
                    <input type="number" id="aura_amount" name="aura_amount" 
                           value="<?php echo esc_attr($amount); ?>" 
                           step="0.01" min="0" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th><label for="aura_date"><?php _e('Fecha', 'aura-suite'); ?></label></th>
                <td>
                    <input type="date" id="aura_date" name="aura_date" 
                           value="<?php echo esc_attr($date ? $date : date('Y-m-d')); ?>" 
                           class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th><label for="aura_attachment"><?php _e('Comprobante', 'aura-suite'); ?></label></th>
                <td>
                    <input type="file" id="aura_attachment" name="aura_attachment" accept=".pdf,.jpg,.jpeg,.png">
                    <?php if ($attachment): ?>
                        <p><a href="<?php echo esc_url(wp_get_attachment_url($attachment)); ?>" target="_blank">
                            <?php _e('Ver comprobante actual', 'aura-suite'); ?>
                        </a></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Renderizar metabox de estado
     */
    public static function render_status_metabox($post) {
        $status = get_post_meta($post->ID, '_aura_transaction_status', true);
        $approval_comment = get_post_meta($post->ID, '_aura_approval_comment', true);
        $approver_id = get_post_meta($post->ID, '_aura_approver_id', true);
        
        if (!$status) {
            $status = 'draft';
        }
        
        $statuses = array(
            'draft'    => __('Borrador', 'aura-suite'),
            'pending'  => __('Pendiente de Aprobación', 'aura-suite'),
            'approved' => __('Aprobada', 'aura-suite'),
            'rejected' => __('Rechazada', 'aura-suite'),
        );
        
        ?>
        <div class="aura-status-box">
            <p><strong><?php _e('Estado Actual:', 'aura-suite'); ?></strong> 
               <span class="status-badge status-<?php echo esc_attr($status); ?>">
                   <?php echo esc_html($statuses[$status]); ?>
               </span>
            </p>
            
            <?php if (current_user_can('aura_finance_approve') && $status === 'pending'): ?>
                <?php
                // Verificar que no sea el autor
                if ($post->post_author != get_current_user_id()):
                ?>
                <hr>
                <h4><?php _e('Acciones de Aprobación', 'aura-suite'); ?></h4>
                <p>
                    <label>
                        <input type="radio" name="aura_approval_action" value="approve">
                        <?php _e('Aprobar', 'aura-suite'); ?>
                    </label><br>
                    <label>
                        <input type="radio" name="aura_approval_action" value="reject">
                        <?php _e('Rechazar', 'aura-suite'); ?>
                    </label>
                </p>
                <p>
                    <label for="aura_approval_comment"><?php _e('Comentario:', 'aura-suite'); ?></label><br>
                    <textarea id="aura_approval_comment" name="aura_approval_comment" 
                              rows="3" class="widefat"></textarea>
                </p>
                <?php else: ?>
                <p class="description">
                    <?php _e('No puedes aprobar tus propias transacciones.', 'aura-suite'); ?>
                </p>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($status === 'approved' || $status === 'rejected'): ?>
                <hr>
                <p><strong><?php _e('Procesado por:', 'aura-suite'); ?></strong><br>
                   <?php 
                   $approver = get_user_by('id', $approver_id);
                   echo $approver ? $approver->display_name : __('Desconocido', 'aura-suite');
                   ?>
                </p>
                <?php if ($approval_comment): ?>
                <p><strong><?php _e('Comentario:', 'aura-suite'); ?></strong><br>
                   <?php echo esc_html($approval_comment); ?>
                </p>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($status === 'draft' && current_user_can('aura_finance_create')): ?>
                <hr>
                <p>
                    <label>
                        <input type="checkbox" name="aura_submit_for_approval" value="1">
                        <?php _e('Enviar a aprobación', 'aura-suite'); ?>
                    </label>
                </p>
            <?php endif; ?>
        </div>
        
        <style>
            .status-badge {
                padding: 4px 8px;
                border-radius: 4px;
                font-weight: bold;
                display: inline-block;
            }
            .status-draft { background: #e5e7eb; color: #374151; }
            .status-pending { background: #fef3c7; color: #92400e; }
            .status-approved { background: #d1fae5; color: #065f46; }
            .status-rejected { background: #fee2e2; color: #991b1b; }
        </style>
        <?php
    }
    
    /**
     * Guardar metadata de la transacción
     */
    public static function save_transaction_meta($post_id, $post) {
        // Verificar nonce
        if (!isset($_POST['aura_transaction_meta_nonce']) || 
            !wp_verify_nonce($_POST['aura_transaction_meta_nonce'], 'aura_transaction_meta')) {
            return;
        }
        
        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Guardar monto
        if (isset($_POST['aura_amount'])) {
            update_post_meta($post_id, '_aura_transaction_amount', sanitize_text_field($_POST['aura_amount']));
        }
        
        // Guardar fecha
        if (isset($_POST['aura_date'])) {
            update_post_meta($post_id, '_aura_transaction_date', sanitize_text_field($_POST['aura_date']));
        }
        
        // Manejar archivo adjunto
        if (isset($_FILES['aura_attachment']) && $_FILES['aura_attachment']['size'] > 0) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $attachment_id = media_handle_upload('aura_attachment', $post_id);
            if (!is_wp_error($attachment_id)) {
                update_post_meta($post_id, '_aura_transaction_attachment', $attachment_id);
            }
        }
        
        // Manejar envío a aprobación
        if (isset($_POST['aura_submit_for_approval']) && $_POST['aura_submit_for_approval'] == '1') {
            update_post_meta($post_id, '_aura_transaction_status', 'pending');
            
            // Enviar notificación a aprobadores
            Aura_Notifications::send_approval_pending_notification($post_id);
        }
        
        // Manejar aprobación/rechazo
        if (isset($_POST['aura_approval_action']) && current_user_can('aura_finance_approve')) {
            // Verificar que no sea el autor
            if ($post->post_author != get_current_user_id()) {
                $action = sanitize_text_field($_POST['aura_approval_action']);
                $comment = isset($_POST['aura_approval_comment']) ? sanitize_textarea_field($_POST['aura_approval_comment']) : '';
                
                if ($action === 'approve') {
                    update_post_meta($post_id, '_aura_transaction_status', 'approved');
                } elseif ($action === 'reject') {
                    update_post_meta($post_id, '_aura_transaction_status', 'rejected');
                }
                
                update_post_meta($post_id, '_aura_approval_comment', $comment);
                update_post_meta($post_id, '_aura_approver_id', get_current_user_id());
                update_post_meta($post_id, '_aura_approval_date', current_time('mysql'));
                
                // Enviar notificación al autor
                Aura_Notifications::send_approval_result_notification($post_id, $action, $comment);
            }
        }
    }
    
    /**
     * Manejar transiciones de estado
     */
    public static function handle_status_transition($new_status, $old_status, $post) {
        if ($post->post_type !== 'aura_transaction') {
            return;
        }
        
        // Lógica adicional para transiciones si es necesario
    }
    
    /**
     * Columnas personalizadas en el listado
     */
    public static function custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['amount'] = __('Monto', 'aura-suite');
        $new_columns['type'] = __('Tipo', 'aura-suite');
        $new_columns['status'] = __('Estado', 'aura-suite');
        $new_columns['date'] = __('Fecha', 'aura-suite');
        $new_columns['author'] = $columns['author'];
        
        return $new_columns;
    }
    
    /**
     * Contenido de columnas personalizadas
     */
    public static function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'amount':
                $amount = get_post_meta($post_id, '_aura_transaction_amount', true);
                echo '$' . number_format($amount, 2);
                break;
                
            case 'type':
                $terms = get_the_terms($post_id, 'aura_transaction_type');
                if ($terms && !is_wp_error($terms)) {
                    echo esc_html($terms[0]->name);
                }
                break;
                
            case 'status':
                $status = get_post_meta($post_id, '_aura_transaction_status', true);
                $statuses = array(
                    'draft'    => __('Borrador', 'aura-suite'),
                    'pending'  => __('Pendiente', 'aura-suite'),
                    'approved' => __('Aprobada', 'aura-suite'),
                    'rejected' => __('Rechazada', 'aura-suite'),
                );
                echo '<span class="status-badge status-' . esc_attr($status) . '">' . 
                     esc_html($statuses[$status] ?? __('Borrador', 'aura-suite')) . '</span>';
                break;
                
            case 'date':
                $date = get_post_meta($post_id, '_aura_transaction_date', true);
                echo $date ? date_i18n(get_option('date_format'), strtotime($date)) : '—';
                break;
        }
    }
    
    /**
     * Agregar páginas de menú
     */
    public static function add_menu_pages() {
        // Página de creación rápida
        if (current_user_can('aura_finance_create')) {
            add_submenu_page(
                'aura-suite',
                __('Nueva Transacción', 'aura-suite'),
                __('Nueva Transacción', 'aura-suite'),
                'aura_finance_create',
                'post-new.php?post_type=aura_transaction'
            );
        }
        
        // Página de listado
        if (current_user_can('aura_finance_view_own') || current_user_can('aura_finance_view_all')) {
            add_submenu_page(
                'aura-suite',
                __('Transacciones', 'aura-suite'),
                __('Transacciones', 'aura-suite'),
                'read',
                'edit.php?post_type=aura_transaction'
            );
        }
        
        // Dashboard financiero
        if (current_user_can('aura_finance_charts')) {
            add_submenu_page(
                'aura-suite',
                __('Dashboard Financiero', 'aura-suite'),
                __('Dashboard Financiero', 'aura-suite'),
                'aura_finance_charts',
                'aura-financial-dashboard',
                array('Aura_Financial_Dashboard', 'render')
            );
        }
    }
}
