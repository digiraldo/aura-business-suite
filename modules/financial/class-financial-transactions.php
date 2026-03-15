<?php
/**
 * Clase para gestionar transacciones financieras
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Aura_Financial_Transactions
 * Gestiona el CRUD de transacciones financieras
 */
class Aura_Financial_Transactions {
    
    /**
     * Inicializar la clase
     */
    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_aura_save_transaction', array(__CLASS__, 'ajax_save_transaction'));
        add_action('wp_ajax_aura_get_categories_by_type', array(__CLASS__, 'ajax_get_categories_by_type'));
        add_action('wp_ajax_aura_upload_receipt', array(__CLASS__, 'ajax_upload_receipt'));
        // Fase 6, Item 6.1: Búsqueda de usuarios WP para autocomplete
        add_action('wp_ajax_aura_search_users', array(__CLASS__, 'ajax_search_users'));
        
        // Migración: agregar columna deleted_by si no existe
        add_action('admin_init', array(__CLASS__, 'maybe_migrate_deleted_by'));
        // Migración: agregar columnas related_user_id y related_user_concept (Fase 6)
        add_action('admin_init', array(__CLASS__, 'maybe_migrate_related_user'));
        // Migración: agregar columna area_id (Fase 8.2)
        add_action('admin_init', array(__CLASS__, 'maybe_migrate_area_id'));
        // Migración: agregar columna expense_category_id (Fase 8.4)
        add_action('admin_init', array(__CLASS__, 'maybe_migrate_expense_category_id'));
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue scripts y styles
     */
    public static function enqueue_scripts($hook) {
        // Solo cargar en la página de nueva transacción
        if ('aura-suite_page_aura-financial-new-transaction' !== $hook && 'toplevel_page_aura-suite' !== $hook) {
            return;
        }
        
        // jQuery UI Datepicker
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css');
        
        // Scripts personalizados
        wp_enqueue_script(
            'aura-transaction-form',
            AURA_PLUGIN_URL . 'assets/js/transaction-form.js',
            array('jquery', 'jquery-ui-datepicker'),
            AURA_VERSION,
            true
        );
        
        // Styles personalizados
        wp_enqueue_style(
            'aura-transaction-form',
            AURA_PLUGIN_URL . 'assets/css/transaction-form.css',
            array(),
            AURA_VERSION
        );
        
        // Localizar script
        // Construir mapa category_id => [area_id, ...] para auto-filtrado inverso (Fase 8.2)
        global $wpdb;
        $budgeted_map  = [];
        $budget_rows   = $wpdb->get_results(
            "SELECT DISTINCT category_id, area_id
             FROM {$wpdb->prefix}aura_finance_budgets
             WHERE is_active = 1
               AND category_id IS NOT NULL
               AND area_id    IS NOT NULL"
        );
        foreach ( $budget_rows as $br ) {
            $budgeted_map[ (int) $br->category_id ][] = (int) $br->area_id;
        }

        wp_localize_script('aura-transaction-form', 'auraTransactionData', array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('aura_transaction_nonce'),
            'budgetsNonce' => wp_create_nonce('aura_budgets_nonce'),
            'messages' => array(
                'saving'                  => __('Guardando transacción...', 'aura-suite'),
                'success'                 => __('Transacción guardada exitosamente', 'aura-suite'),
                'error'                   => __('Error al guardar la transacción', 'aura-suite'),
                'uploadError'             => __('Error al subir el archivo', 'aura-suite'),
                'confirmLeave'            => __('Tienes cambios sin guardar. ¿Deseas salir?', 'aura-suite'),
                'noBudgetsForArea'        => __('Esta área no tiene presupuestos asignados para la fecha actual.', 'aura-suite'),
                'noBudgetForCat'          => __('No hay presupuesto activo para esta categoría en el área seleccionada.', 'aura-suite'),
                'overspend'               => __('⚠️ Este monto supera el disponible del presupuesto', 'aura-suite'),
                'loadingCats'             => __('Cargando categorías...', 'aura-suite'),
                'areaFilteredByCategory'  => __('Mostrando áreas con presupuesto para esta categoría.', 'aura-suite'),
            ),
            'maxFileSize'             => 5242880, // 5MB en bytes
            'allowedFileTypes'        => array('jpg', 'jpeg', 'png', 'pdf'),
            'budgetedAreasByCategory' => $budgeted_map,
        ));
    }
    
    /**
     * AJAX: Guardar transacción
     */
    public static function ajax_save_transaction() {
        // Verificar nonce
        check_ajax_referer('aura_transaction_nonce', 'nonce');
        
        // Verificar permisos
        if (!current_user_can('aura_finance_create')) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para crear transacciones', 'aura-suite')
            ));
        }
        
        // Obtener datos del POST
        $transaction_type = sanitize_text_field($_POST['transaction_type'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $transaction_date = sanitize_text_field($_POST['transaction_date'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $payment_method = sanitize_text_field($_POST['payment_method'] ?? '');
        $reference_number = sanitize_text_field($_POST['reference_number'] ?? '');
        $recipient_payer = sanitize_text_field($_POST['recipient_payer'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $tags = sanitize_text_field($_POST['tags'] ?? '');
        $receipt_file = sanitize_text_field($_POST['receipt_file'] ?? '');
        // Fase 6, Item 6.1: usuario vinculado
        $related_user_id = intval($_POST['related_user_id'] ?? 0);
        $related_user_concept = sanitize_key($_POST['related_user_concept'] ?? '');
        // Fase 8.4: categoría detallada del gasto (qué se compró / por qué ingresó)
        $expense_category_id = intval($_POST['expense_category_id'] ?? 0);

        // Fase 8.2: área / programa
        $current_uid = get_current_user_id();
        $area_id     = null;
        if ( current_user_can( 'aura_areas_view_own' )
             && ! current_user_can( 'aura_areas_view_all' )
             && ! current_user_can( 'manage_options' ) ) {
            // Forzar el área del responsable
            global $wpdb;
            $area_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aura_areas WHERE responsible_user_id = %d AND status = 'active' LIMIT 1",
                $current_uid
            ) ) ?: null;
        } elseif ( ! empty( $_POST['area_id'] ) ) {
            $area_id = absint( $_POST['area_id'] ) ?: null;
        }
        
        // Validaciones
        $errors = array();
        
        if (empty($transaction_type) || !in_array($transaction_type, array('income', 'expense'))) {
            $errors[] = __('Tipo de transacción inválido', 'aura-suite');
        }

        // expense_category_id es obligatorio (detalle del gasto)
        if ($expense_category_id <= 0) {
            $errors[] = __('Debe seleccionar la categoría del gasto', 'aura-suite');
        }

        // category_id es opcional (presupuesto del área); si está vacío se usará expense_category_id
        // No se valida como obligatorio aquí.
        
        if ($amount <= 0) {
            $errors[] = __('El monto debe ser mayor a 0', 'aura-suite');
        }
        
        if (empty($transaction_date)) {
            $errors[] = __('La fecha es requerida', 'aura-suite');
        }
        
        if (strlen($description) < 10) {
            $errors[] = __('La descripción debe tener al menos 10 caracteres', 'aura-suite');
        }
        
        // Si hay errores, enviar respuesta
        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => implode('<br>', $errors)
            ));
        }
        
        // Verificar que la categoría del gasto (expense_category_id) existe y coincide con el tipo
        global $wpdb;
        $table_name = $wpdb->prefix . 'aura_finance_categories';

        $expense_category = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND is_active = 1",
            $expense_category_id
        ) );

        if ( ! $expense_category ) {
            wp_send_json_error( array(
                'message' => __( 'La categoría del gasto seleccionada no es válida o está inactiva', 'aura-suite' )
            ) );
        }

        if ( $expense_category->type !== 'both' && $expense_category->type !== $transaction_type ) {
            wp_send_json_error( array(
                'message' => __( 'La categoría del gasto no corresponde al tipo de transacción (ingreso/egreso)', 'aura-suite' )
            ) );
        }

        // Verificar la categoría del presupuesto (category_id) solo si fue seleccionada
        $category = null;
        if ( $category_id > 0 ) {
            $category = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND is_active = 1",
                $category_id
            ) );
            if ( ! $category ) {
                wp_send_json_error( array(
                    'message' => __( 'La categoría del presupuesto no es válida o está inactiva', 'aura-suite' )
                ) );
            }
        }

        // Si no hay category_id explícito, usar expense_category_id como referencia para aprobación
        $effective_category_id = $category_id > 0 ? $category_id : $expense_category_id;
        
        // Insertar en la base de datos
        $transactions_table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Campos de integración (opcionales, usados cuando otros módulos crean transacciones)
        $related_module = sanitize_text_field($_POST['related_module'] ?? '');
        $related_item_id = intval($_POST['related_item_id'] ?? 0);
        $related_action = sanitize_text_field($_POST['related_action'] ?? '');
        
        // SISTEMA DE APROBACIÓN AUTOMÁTICA (Item 2.6)
        // Determinar estado inicial basado en configuración de umbral
        $transaction_data = array(
            'transaction_type' => $transaction_type,
            'category_id' => $effective_category_id,
            'amount' => $amount,
            'area_id' => $area_id,
            'related_module' => !empty($related_module) ? $related_module : null,
            'related_action' => !empty($related_action) ? $related_action : null,
        );
        
        $initial_status = Aura_Financial_Settings::determine_initial_status($transaction_data);
        $current_user_id = get_current_user_id();
        
        // Preparar datos de inserción
        $insert_data = array(
            'transaction_type' => $transaction_type,
            'category_id' => $effective_category_id,  // presupuesto o fallback a gasto
            'amount' => $amount,
            'transaction_date' => $transaction_date,
            'description' => $description,
            'notes' => $notes,
            'status' => $initial_status,
            'payment_method' => $payment_method,
            'reference_number' => $reference_number,
            'recipient_payer' => $recipient_payer,
            'receipt_file' => $receipt_file,
            'tags' => $tags,
            'related_module' => !empty($related_module) ? $related_module : null,
            'related_item_id' => $related_item_id > 0 ? $related_item_id : null,
            'related_action' => !empty($related_action) ? $related_action : null,
            // Fase 6, Item 6.1
            'related_user_id'      => $related_user_id > 0 ? $related_user_id : null,
            'related_user_concept' => !empty($related_user_concept) ? $related_user_concept : null,
            // Fase 8.2: área / programa
            'area_id'              => $area_id,
            // Fase 8.4: categoría detallada del gasto
            'expense_category_id'  => $expense_category_id > 0 ? $expense_category_id : null,
            'created_by' => $current_user_id,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );
        
        $insert_formats = array(
            '%s', // transaction_type
            '%d', // category_id
            '%f', // amount
            '%s', // transaction_date
            '%s', // description
            '%s', // notes
            '%s', // status
            '%s', // payment_method
            '%s', // reference_number
            '%s', // recipient_payer
            '%s', // receipt_file
            '%s', // tags
            '%s', // related_module
            '%d', // related_item_id
            '%s', // related_action
            '%d', // related_user_id (Fase 6)
            '%s', // related_user_concept (Fase 6)
            '%d', // area_id (Fase 8.2)
            '%d', // expense_category_id (Fase 8.4)
            '%d', // created_by
            '%s', // created_at
            '%s', // updated_at
        );
        
        // Si fue auto-aprobada, agregar campos de aprobación
        if ($initial_status === 'approved') {
            $insert_data['approved_by'] = $current_user_id;
            $insert_data['approved_at'] = current_time('mysql');
            $insert_formats[] = '%d'; // approved_by
            $insert_formats[] = '%s'; // approved_at
        }
        
        $result = $wpdb->insert(
            $transactions_table,
            $insert_data,
            $insert_formats
        );
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Error al guardar la transacción en la base de datos', 'aura-suite'),
                'error' => $wpdb->last_error
            ));
        }
        
        $transaction_id = $wpdb->insert_id;
        
        // Hook para extensiones
        do_action('aura_finance_transaction_created', $transaction_id, $transaction_type, $amount);
        
        // Preparar mensaje de respuesta según el estado
        if ($initial_status === 'approved') {
            // Transacción auto-aprobada
            $threshold = (float) get_option('aura_finance_auto_approval_threshold', 0);
            $message = sprintf(
                __('✅ Transacción aprobada automáticamente (Monto: $%s, Umbral: $%s)', 'aura-suite'),
                number_format($amount, 2),
                number_format($threshold, 2)
            );
            
            // Registrar en historial de auditoría
            $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
            $wpdb->insert(
                $history_table,
                array(
                    'transaction_id' => $transaction_id,
                    'field_changed' => 'status',
                    'old_value' => '-',
                    'new_value' => 'approved (Auto-aprobada)',
                    'change_reason' => sprintf(
                        'Auto-aprobada por estar bajo el umbral configurado de $%s',
                        number_format($threshold, 2)
                    ),
                    'changed_by' => $current_user_id,
                    'changed_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
            );
            
            // Hook específico para auto-aprobación
            do_action('aura_finance_transaction_auto_approved', $transaction_id, $amount, $threshold);
            
        } else {
            // Transacción pendiente de aprobación
            $message = __('Transacción guardada exitosamente. Está pendiente de aprobación.', 'aura-suite');
            
            // Notificar a aprobadores (hook existente)
            do_action('aura_finance_transaction_pending_approval', $transaction_id);
        }
        
        // Respuesta exitosa
        wp_send_json_success(array(
            'message' => $message,
            'transaction_id' => $transaction_id,
            'status' => $initial_status,
            'is_auto_approved' => $initial_status === 'approved',
            'redirect_url' => admin_url('admin.php?page=aura-financial-transactions')
        ));
    }
    
    /**
     * AJAX: Obtener categorías por tipo
     */
    public static function ajax_get_categories_by_type() {
        check_ajax_referer('aura_transaction_nonce', 'nonce');
        
        $type = sanitize_text_field($_POST['type'] ?? $_POST['transaction_type'] ?? '');
        
        if (!in_array($type, array('income', 'expense'))) {
            wp_send_json_error(array(
                'message' => __('Tipo inválido', 'aura-suite')
            ));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aura_finance_categories';
        
        $categories = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, parent_id, color, icon FROM $table_name 
             WHERE (type = %s OR type = 'both') AND is_active = 1 
             ORDER BY display_order ASC, name ASC",
            $type
        ));
        
        // Organizar en estructura jerárquica
        $hierarchy = self::build_category_hierarchy($categories);
        
        wp_send_json_success(array(
            'categories' => $hierarchy
        ));
    }
    
    /**
     * AJAX: Subir comprobante
     */
    public static function ajax_upload_receipt() {
        check_ajax_referer('aura_transaction_nonce', 'nonce');
        
        if (!current_user_can('aura_finance_create')) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para subir archivos', 'aura-suite')
            ));
        }
        
        if (!isset($_FILES['receipt_file'])) {
            wp_send_json_error(array(
                'message' => __('No se recibió ningún archivo', 'aura-suite')
            ));
        }
        
        // Validar archivo
        $file = $_FILES['receipt_file'];
        $allowed_types = array('image/jpeg', 'image/png', 'application/pdf');
        $max_size = 5242880; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(array(
                'message' => __('Tipo de archivo no permitido. Solo JPG, PNG o PDF', 'aura-suite')
            ));
        }
        
        if ($file['size'] > $max_size) {
            wp_send_json_error(array(
                'message' => __('El archivo excede el tamaño máximo de 5MB', 'aura-suite')
            ));
        }
        
        // Crear directorio personalizado para recibos
        $upload_dir = wp_upload_dir();
        $custom_dir = $upload_dir['basedir'] . '/aura-finance/receipts';
        $custom_url = $upload_dir['baseurl'] . '/aura-finance/receipts';
        
        // Crear directorio si no existe
        if (!file_exists($custom_dir)) {
            wp_mkdir_p($custom_dir);
            
            // Crear archivo .htaccess para proteger acceso directo
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<FilesMatch \"\\.(jpg|jpeg|png|pdf)$\">\n";
            $htaccess_content .= "  Order Allow,Deny\n";
            $htaccess_content .= "  Allow from all\n";
            $htaccess_content .= "</FilesMatch>";
            file_put_contents($custom_dir . '/.htaccess', $htaccess_content);
        }
        
        // Subir archivo usando WordPress con directorio personalizado
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        // Generar nombre único para el archivo
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_filename = wp_unique_filename($custom_dir, time() . '_' . sanitize_file_name($file['name']));
        
        // Filtro para cambiar el directorio de subida
        add_filter('upload_dir', function($dirs) use ($custom_dir, $custom_url) {
            $dirs['path'] = $custom_dir;
            $dirs['url'] = $custom_url;
            $dirs['subdir'] = '';
            return $dirs;
        });
        
        $upload = wp_handle_upload($file, array('test_form' => false));
        
        // Remover el filtro
        remove_all_filters('upload_dir');
        
        if (isset($upload['error'])) {
            wp_send_json_error(array(
                'message' => $upload['error']
            ));
        }
        
        // Extraer solo el nombre del archivo (sin la ruta completa)
        $filename = basename($upload['file']);
        
        wp_send_json_success(array(
            'file_url' => $upload['url'],
            'file_path' => $filename, // Solo el nombre del archivo
            'filename' => $filename
        ));
    }
    
    /**
     * Construir jerarquía de categorías
     */
    private static function build_category_hierarchy($categories, $parent_id = null) {
        $hierarchy = array();
        
        foreach ($categories as $category) {
            if ($category->parent_id == $parent_id) {
                $children = self::build_category_hierarchy($categories, $category->id);
                
                $item = array(
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color,
                    'icon' => $category->icon,
                );
                
                if (!empty($children)) {
                    $item['children'] = $children;
                }
                
                $hierarchy[] = $item;
            }
        }
        
        return $hierarchy;
    }
    
    /**
     * Obtener transacción por ID
     */
    public static function get_transaction($transaction_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aura_finance_transactions';
        
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $transaction_id
        ));
        
        return $transaction;
    }
    
    /**
     * Obtener transacciones con filtros
     */
    public static function get_transactions($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aura_finance_transactions';
        
        $defaults = array(
            'type' => '',
            'status' => '',
            'category_id' => 0,
            'start_date' => '',
            'end_date' => '',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'transaction_date',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('deleted_at IS NULL');
        
        if (!empty($args['type'])) {
            $where[] = $wpdb->prepare('transaction_type = %s', $args['type']);
        }
        
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare('status = %s', $args['status']);
        }
        
        if ($args['category_id'] > 0) {
            $where[] = $wpdb->prepare('category_id = %d', $args['category_id']);
        }
        
        if (!empty($args['start_date'])) {
            $where[] = $wpdb->prepare('transaction_date >= %s', $args['start_date']);
        }
        
        if (!empty($args['end_date'])) {
            $where[] = $wpdb->prepare('transaction_date <= %s', $args['end_date']);
        }
        
        $where_clause = implode(' AND ', $where);
        $order_clause = sprintf('ORDER BY %s %s', $args['orderby'], $args['order']);
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        
        $sql = "SELECT * FROM $table_name WHERE $where_clause $order_clause $limit_clause";
        
        $transactions = $wpdb->get_results($sql);
        
        return $transactions;
    }
    
    /**
     * Crear transacción vinculada a otro módulo (método público para integraciones)
     * 
     * Este método permite a otros módulos (Inventario, Estudiantes, etc.) crear
     * transacciones financieras automáticamente con vinculación bidireccional.
     * 
     * @since 1.0.0
     * @param array $args Argumentos de la transacción
     *   @type string $transaction_type     'income' o 'expense' (requerido)
     *   @type int    $category_id          ID de categoría financiera (requerido)
     *   @type float  $amount               Monto de la transacción (requerido)
     *   @type string $description          Descripción (requerido)
     *   @type string $transaction_date     Fecha formato Y-m-d (opcional, default hoy)
     *   @type string $related_module       'inventory', 'students', 'library', 'vehicles' (requerido)
     *   @type int    $related_item_id      ID del item en módulo relacionado (requerido)
     *   @type string $related_action       'purchase', 'maintenance', 'payment', 'enrollment', etc. (requerido)
     *   @type string $notes                Notas adicionales (opcional)
     *   @type string $payment_method       Método de pago (opcional)
     *   @type string $reference_number     Número de referencia (opcional)
     *   @type string $recipient_payer      Beneficiario/Pagador (opcional)
     *   @type string $status               'pending', 'approved', 'rejected' (opcional, default 'approved')
     * 
     * @return int|false ID de transacción creada o false en error
     * 
     * @example
     * // Ejemplo 1: Mantenimiento de inventario
     * $transaction_id = Aura_Financial_Transactions::create_related_transaction(array(
     *     'transaction_type' => 'expense',
     *     'category_id' => 15, // ID de "Mantenimiento → Herramientas de Motor"
     *     'amount' => 150.00,
     *     'description' => 'Mantenimiento externo de motoguadaña Yamaha',
     *     'related_module' => 'inventory',
     *     'related_item_id' => 45, // ID del equipo
     *     'related_action' => 'maintenance',
     *     'payment_method' => 'Efectivo',
     *     'status' => 'approved'
     * ));
     * 
     * @example
     * // Ejemplo 2: Pago de estudiante
     * $transaction_id = Aura_Financial_Transactions::create_related_transaction(array(
     *     'transaction_type' => 'income',
     *     'category_id' => 8, // ID de "Inscripciones → Inscripción de Estudiantes"
     *     'amount' => 100.00,
     *     'description' => 'Pago de cuota #2 - Juan Pérez',
     *     'related_module' => 'students',
     *     'related_item_id' => 23, // ID del estudiante
     *     'related_action' => 'payment',
     *     'status' => 'approved'
     * ));
     */
    public static function create_related_transaction($args) {
        global $wpdb;
        
        // Validar campos requeridos
        $required = array('transaction_type', 'category_id', 'amount', 'description', 'related_module', 'related_item_id', 'related_action');
        foreach ($required as $field) {
            if (empty($args[$field])) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) {
                    error_log("AURA Finance: Campo requerido faltante: $field");
                }
                return false;
            }
        }
        
        // Validar tipo de transacción
        if (!in_array($args['transaction_type'], array('income', 'expense'))) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log("AURA Finance: Tipo de transacción inválido: {$args['transaction_type']}");
            }
            return false;
        }
        
        // Validar módulo relacionado
        $valid_modules = array('inventory', 'students', 'library', 'vehicles', 'forms');
        if (!in_array($args['related_module'], $valid_modules)) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log("AURA Finance: Módulo relacionado inválido: {$args['related_module']}");
            }
            return false;
        }
        
        // Preparar datos con valores por defecto
        $transaction_data = array(
            'transaction_type'   => sanitize_text_field($args['transaction_type']),
            'category_id'        => intval($args['category_id']),
            'amount'             => floatval($args['amount']),
            'transaction_date'   => isset($args['transaction_date']) ? sanitize_text_field($args['transaction_date']) : current_time('Y-m-d'),
            'description'        => sanitize_textarea_field($args['description']),
            'notes'              => isset($args['notes']) ? sanitize_textarea_field($args['notes']) : '',
            'status'             => isset($args['status']) ? sanitize_text_field($args['status']) : 'approved',
            'payment_method'     => isset($args['payment_method']) ? sanitize_text_field($args['payment_method']) : '',
            'reference_number'   => isset($args['reference_number']) ? sanitize_text_field($args['reference_number']) : '',
            'recipient_payer'    => isset($args['recipient_payer']) ? sanitize_text_field($args['recipient_payer']) : '',
            'related_module'     => sanitize_text_field($args['related_module']),
            'related_item_id'    => intval($args['related_item_id']),
            'related_action'     => sanitize_text_field($args['related_action']),
            'created_by'         => get_current_user_id() > 0 ? get_current_user_id() : 1,
            'created_at'         => current_time('mysql'),
            'updated_at'         => current_time('mysql'),
        );
        
        // Si el estado es 'approved', agregar datos de aprobación
        if ($transaction_data['status'] === 'approved') {
            $transaction_data['approved_by'] = $transaction_data['created_by'];
            $transaction_data['approved_at'] = current_time('mysql');
        }
        
        // Insertar en base de datos
        $transactions_table = $wpdb->prefix . 'aura_finance_transactions';
        $result = $wpdb->insert($transactions_table, $transaction_data);
        
        if ($result === false) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log("AURA Finance: Error al crear transacción relacionada - " . $wpdb->last_error);
            }
            return false;
        }
        
        $transaction_id = $wpdb->insert_id;
        
        // Hook para extensiones
        do_action('aura_finance_related_transaction_created', $transaction_id, $args['related_module'], $args['related_item_id']);
        
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log("AURA Finance: Transacción relacionada creada - ID: $transaction_id, Módulo: {$args['related_module']}, Item: {$args['related_item_id']}");
        }
        
        return $transaction_id;
    }
    
    /**
     * Crear tablas de transacciones financieras en la base de datos
     * Se ejecuta en el hook de activación del plugin
     * 
     * Crea 3 tablas:
     * 1. wp_aura_finance_transactions - Transacciones principales
     * 2. wp_aura_finance_budgets - Presupuestos por categoría
     * 3. wp_aura_finance_transaction_history - Historial de cambios (auditoría)
     * 
     * @since 1.0.0
     * @return void
     */
    public static function create_transactions_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla principal de transacciones financieras
        $table_transactions = $wpdb->prefix . 'aura_finance_transactions';
        
        $sql_transactions = "CREATE TABLE IF NOT EXISTS $table_transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            transaction_type ENUM('income', 'expense') NOT NULL,
            category_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(15, 2) NOT NULL,
            transaction_date DATE NOT NULL,
            description TEXT NOT NULL,
            notes TEXT,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            payment_method VARCHAR(50),
            reference_number VARCHAR(100),
            recipient_payer VARCHAR(255),
            receipt_file VARCHAR(255),
            tags VARCHAR(500),
            related_module ENUM('inventory', 'library', 'vehicles', 'forms', 'students') NULL COMMENT 'Módulo que generó transacción',
            related_item_id BIGINT UNSIGNED NULL COMMENT 'ID del item en módulo relacionado',
            related_action VARCHAR(50) NULL COMMENT 'Acción: purchase, maintenance, rental, loan, payment, enrollment',
            created_by BIGINT UNSIGNED NOT NULL,
            approved_by BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,
            rejection_reason TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            deleted_by BIGINT UNSIGNED NULL,
            INDEX idx_type (transaction_type),
            INDEX idx_category (category_id),
            INDEX idx_status (status),
            INDEX idx_date (transaction_date),
            INDEX idx_deleted (deleted_at),
            INDEX idx_related (related_module, related_item_id),
            INDEX idx_created_by (created_by)
        ) $charset_collate;";
        
        // Tabla de presupuestos por categoría
        $table_budgets = $wpdb->prefix . 'aura_finance_budgets';
        
        $sql_budgets = "CREATE TABLE IF NOT EXISTS $table_budgets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id BIGINT UNSIGNED NOT NULL,
            budget_amount DECIMAL(15, 2) NOT NULL,
            period_type ENUM('monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            alert_threshold INT DEFAULT 80 COMMENT 'Porcentaje de alerta cuando se alcanza',
            is_active BOOLEAN DEFAULT 1,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category (category_id),
            INDEX idx_active (is_active),
            INDEX idx_period (start_date, end_date)
        ) $charset_collate;";
        
        // Tabla de historial de cambios (auditoría de ediciones)
        $table_history = $wpdb->prefix . 'aura_finance_transaction_history';
        
        $sql_history = "CREATE TABLE IF NOT EXISTS $table_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            transaction_id BIGINT UNSIGNED NOT NULL,
            field_changed VARCHAR(100) NOT NULL COMMENT 'Nombre del campo modificado',
            old_value TEXT COMMENT 'Valor anterior',
            new_value TEXT COMMENT 'Valor nuevo',
            changed_by BIGINT UNSIGNED NOT NULL,
            change_reason TEXT COMMENT 'Motivo del cambio (opcional)',
            changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_transaction (transaction_id),
            INDEX idx_changed_at (changed_at),
            INDEX idx_changed_by (changed_by)
        ) $charset_collate;";
        
        // Ejecutar creación de tablas usando dbDelta para manejo seguro
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_transactions);
        dbDelta($sql_budgets);
        dbDelta($sql_history);
        
        // Registrar versiones de las tablas
        add_option('aura_finance_transactions_db_version', '1.0');
        add_option('aura_finance_budgets_db_version', '1.0');
        add_option('aura_finance_transaction_history_db_version', '1.0');
        
        // Log de éxito (solo en modo debug)
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('AURA: Tablas financieras creadas exitosamente - transactions, budgets, transaction_history');
        }
    }
    
    /**
     * Migración: agregar columna deleted_by a la tabla de transacciones si no existe
     * Se ejecuta en admin_init para instalaciones existentes
     *
     * @since 1.0.0
     * @return void
     */
    public static function maybe_migrate_deleted_by() {
        // Comprobar si ya se hizo esta migración
        if (get_option('aura_finance_deleted_by_migrated')) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Verificar si la columna ya existe
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'deleted_by'",
            DB_NAME,
            $table
        ));

        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER deleted_at");
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log('AURA: Columna deleted_by agregada a ' . $table);
            }
        }

        update_option('aura_finance_deleted_by_migrated', '1.0');
    }

    /**
     * Migración Fase 6, Item 6.1: agregar related_user_id y related_user_concept
     * si las columnas no existen en la tabla de transacciones.
     *
     * @since 1.0.1
     * @return void
     */
    public static function maybe_migrate_related_user(): void {
        if ( get_option( 'aura_finance_related_user_migrated' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $columns = $wpdb->get_col( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME,
            $table
        ) );

        if ( ! in_array( 'related_user_id', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN related_user_id BIGINT UNSIGNED NULL AFTER recipient_payer" );
            if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log( 'AURA: Columna related_user_id agregada a ' . $table ); }
        }

        if ( ! in_array( 'related_user_concept', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN related_user_concept VARCHAR(100) NULL AFTER related_user_id" );
            if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log( 'AURA: Columna related_user_concept agregada a ' . $table ); }
        }

        if ( ! in_array( 'related_user_id', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_related_user (related_user_id)" );
        }

        update_option( 'aura_finance_related_user_migrated', '1.0' );
    }

    /**
     * Migración Fase 8.2: agregar columna area_id a la tabla de transacciones
     * si la columna no existe.
     *
     * @since 1.0.2
     * @return void
     */
    public static function maybe_migrate_area_id(): void {
        if ( get_option( 'aura_finance_area_id_migrated_v1' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $columns = $wpdb->get_col( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME,
            $table
        ) );

        if ( ! in_array( 'area_id', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN area_id BIGINT UNSIGNED NULL AFTER related_user_concept" );
            $wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_area (area_id)" );
            if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log( 'AURA: Columna area_id agregada a ' . $table ); }
        }

        update_option( 'aura_finance_area_id_migrated_v1', '1.0' );
    }

    /**
     * Migración Fase 8.4: agregar columna expense_category_id a la tabla de transacciones.
     * Almacena la categoría detallada que describe en qué se usó el dinero.
     *
     * @since 1.0.3
     * @return void
     */
    public static function maybe_migrate_expense_category_id(): void {
        if ( get_option( 'aura_finance_expense_cat_migrated_v1' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $columns = $wpdb->get_col( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME,
            $table
        ) );

        if ( ! in_array( 'expense_category_id', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN expense_category_id INT NULL DEFAULT NULL AFTER area_id" );
            $wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_expense_cat (expense_category_id)" );
            if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log( 'AURA: Columna expense_category_id agregada a ' . $table ); }
        }

        update_option( 'aura_finance_expense_cat_migrated_v1', '1.0' );
    }

    /**
     * AJAX: Buscar usuarios WordPress para autocomplete (Fase 6, Item 6.1)
     * Capability requerida: aura_finance_link_user o aura_finance_view_all
     *
     * @since 1.0.1
     * @return void
     */
    public static function ajax_search_users(): void {
        check_ajax_referer( 'aura_transaction_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_link_user' ) && ! current_user_can( 'aura_finance_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para buscar usuarios.', 'aura-suite' ) ] );
        }

        $term = sanitize_text_field( $_POST['term'] ?? '' );
        if ( strlen( $term ) < 2 ) {
            wp_send_json_success( [] );
        }

        $users = get_users( [
            'search'         => '*' . $term . '*',
            'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
            'number'         => 10,
            'orderby'        => 'display_name',
            'fields'         => [ 'ID', 'display_name', 'user_email', 'user_login' ],
        ] );

        $result = [];
        foreach ( $users as $user ) {
            $result[] = [
                'id'         => (int) $user->ID,
                'name'       => $user->display_name,
                'email'      => $user->user_email,
                'login'      => $user->user_login,
                'avatar_url' => get_avatar_url( $user->ID, [ 'size' => 32 ] ),
                'label'      => $user->display_name . ' (' . $user->user_email . ')',
                'value'      => $user->display_name,
            ];
        }

        wp_send_json_success( $result );
    }
}

// Inicializar
Aura_Financial_Transactions::init();
