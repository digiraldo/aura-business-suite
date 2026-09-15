<?php
/**
 * Directorio Global de Terceros y Entidades Maestras (Business Partners)
 *
 * Centraliza la gestión de Terceros, Empresas, Tiendas, Fundaciones y Personas Naturales
 * para su uso transversal en Finanzas, Inventario, Vehículos, Biblioteca y demás módulos.
 *
 * @package AuraBusinessSuite
 * @subpackage Common
 * @since 1.7.9
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aura_Third_Parties {

    /**
     * Inicializar hooks y endpoints AJAX
     */
    public static function init() {
        self::ensure_table();

        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));

        // AJAX handlers de terceros
        add_action('wp_ajax_aura_third_parties_list', array(__CLASS__, 'ajax_list'));
        add_action('wp_ajax_aura_third_parties_search', array(__CLASS__, 'ajax_search'));
        add_action('wp_ajax_aura_third_parties_create', array(__CLASS__, 'ajax_create'));
        add_action('wp_ajax_aura_third_parties_quick_create', array(__CLASS__, 'ajax_create'));
        add_action('wp_ajax_aura_third_parties_update', array(__CLASS__, 'ajax_update'));
        add_action('wp_ajax_aura_third_parties_toggle', array(__CLASS__, 'ajax_toggle'));
        add_action('wp_ajax_aura_third_parties_delete', array(__CLASS__, 'ajax_delete'));
        add_action('wp_ajax_aura_third_parties_summary_360', array(__CLASS__, 'ajax_summary_360'));
        add_action('wp_ajax_aura_third_parties_create_wp_user', array(__CLASS__, 'ajax_create_wp_user'));

        // Integración de Avatar de WordPress con logotipo/foto del tercero
        add_filter('pre_get_avatar_data', array(__CLASS__, 'filter_pre_get_avatar_data'), 20, 2);

        // Catálogo para el Modal Explorador de Terceros clasificado por rol contable
        add_action('wp_ajax_aura_third_parties_catalog_by_role', array(__CLASS__, 'ajax_catalog_by_role'));

        // Búsqueda unificada transversal (Terceros + Usuarios WP)
        add_action('wp_ajax_aura_search_counterparties', array(__CLASS__, 'ajax_search_counterparties'));
    }

    /**
     * Catálogo de roles / calificaciones contables para terceros
     */
    public static function get_accounting_roles(): array {
        return array(
            'supplier'      => array('label' => __('Proveedores / Comercios', 'aura-suite'), 'icon' => 'dashicons-cart', 'order' => 1, 'badge' => 'aura-pill--warning'),
            'customer'      => array('label' => __('Clientes / Pagadores', 'aura-suite'), 'icon' => 'dashicons-money-alt', 'order' => 2, 'badge' => 'aura-pill--success'),
            'employee'      => array('label' => __('Empleados / Colaboradores', 'aura-suite'), 'icon' => 'dashicons-businessman', 'order' => 3, 'badge' => 'aura-pill--info'),
            'bank_creditor' => array('label' => __('Bancos / Entidades Financieras', 'aura-suite'), 'icon' => 'dashicons-vault', 'order' => 4, 'badge' => 'aura-pill--primary'),
            'tax_authority' => array('label' => __('Impuestos / DIAN / Estado', 'aura-suite'), 'icon' => 'dashicons-building', 'order' => 5, 'badge' => 'aura-pill--danger'),
            'other'         => array('label' => __('Otros Terceros', 'aura-suite'), 'icon' => 'dashicons-groups', 'order' => 6, 'badge' => 'aura-pill--muted'),
        );
    }

    /**
     * Nombre de la tabla de terceros
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'aura_finance_third_parties';
    }

    /**
     * Asegurar que la tabla y columnas existan
     */
    public static function ensure_table() {
        global $wpdb;
        $table = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(191) NOT NULL,
            party_type VARCHAR(50) NOT NULL DEFAULT 'company',
            accounting_role VARCHAR(50) NOT NULL DEFAULT 'supplier',
            commercial_name VARCHAR(191) NULL,
            document_id VARCHAR(80) NULL,
            tax_id_type VARCHAR(20) NULL DEFAULT 'NIT',
            phone VARCHAR(50) NULL,
            email VARCHAR(100) NULL,
            website VARCHAR(191) NULL,
            address TEXT NULL,
            notes TEXT NULL,
            logo_id BIGINT UNSIGNED NULL DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            wp_user_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_name (full_name),
            KEY idx_party_type (party_type),
            KEY idx_accounting_role (accounting_role),
            KEY idx_active (is_active),
            KEY idx_wp_user (wp_user_id),
            KEY idx_logo (logo_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Migrar columnas adicionales si ya existía la tabla
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (is_array($columns) && !empty($columns)) {
            if (!in_array('accounting_role', $columns, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN accounting_role VARCHAR(50) NOT NULL DEFAULT 'supplier' AFTER party_type");
            }
            if (!in_array('wp_user_id', $columns, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN wp_user_id BIGINT UNSIGNED NULL AFTER is_active");
            }
            if (!in_array('party_type', $columns, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN party_type VARCHAR(50) NOT NULL DEFAULT 'company' AFTER full_name");
            } else {
                // Asegurar que party_type soporte nuevas entidades sin restricción ENUM
                $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN party_type VARCHAR(50) NOT NULL DEFAULT 'company'");
            }
            if (!in_array('commercial_name', $columns, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN commercial_name VARCHAR(191) NULL AFTER party_type");
            }
            if (!in_array('tax_id_type', $columns, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN tax_id_type VARCHAR(20) NULL DEFAULT 'NIT' AFTER document_id");
            }
            if (!in_array('website', $columns, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN website VARCHAR(191) NULL AFTER email");
            }
            if (!in_array('address', $columns, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN address TEXT NULL AFTER website");
            }
            if (!in_array('logo_id', $columns, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN logo_id BIGINT UNSIGNED NULL AFTER notes");
            }
        }
    }

    /**
     * Encolar scripts y estilos necesarios
     */
    public static function enqueue_assets($hook) {
        $current_page = sanitize_key($_GET['page'] ?? '');

        if (strpos($hook, 'aura-third-parties') !== false || $current_page === 'aura-third-parties') {
            wp_enqueue_media();
            wp_enqueue_script('jquery-ui-autocomplete');

            // DataTables
            wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css', array(), '2.2.2');
            wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/2.2.2/js/dataTables.min.js', array('jquery'), '2.2.2', true);

            // Estilos y scripts de administración
            wp_enqueue_style('aura-admin-styles', AURA_PLUGIN_URL . 'assets/css/admin-styles.css', array(), AURA_VERSION);
            $css_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/css/third-parties-directory.css');
            wp_enqueue_style('aura-third-parties-directory-css', AURA_PLUGIN_URL . 'assets/css/third-parties-directory.css', array('aura-admin-styles'), $css_ver);
            
            $js_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/js/third-parties-directory.js');
            wp_enqueue_script('aura-third-parties-directory', AURA_PLUGIN_URL . 'assets/js/third-parties-directory.js', array('jquery'), $js_ver, true);

            wp_localize_script('aura-third-parties-directory', 'auraThirdPartiesData', array(
                'ajaxUrl'       => admin_url('admin-ajax.php'),
                'nonce'         => wp_create_nonce('aura_third_parties_nonce'),
                'canCreate'     => current_user_can('manage_options') || current_user_can('aura_third_parties_create'),
                'canEdit'       => current_user_can('manage_options') || current_user_can('aura_third_parties_edit'),
                'canDelete'     => current_user_can('manage_options') || current_user_can('aura_third_parties_delete'),
                'canCreateUser' => current_user_can('manage_options') || current_user_can('create_users') || current_user_can('aura_third_parties_create_wp_user') || current_user_can('aura_admin_users_create'),
                'i18n'          => array(
                    'confirmDelete'   => __('¿Estás seguro de que deseas eliminar este tercero/empresa?', 'aura-suite'),
                    'confirmToggle'   => __('¿Deseas cambiar el estado de este tercero?', 'aura-suite'),
                    'confirmCreateWP' => __('¿Deseas crear un usuario de WordPress (Suscriptor) para este tercero?', 'aura-suite'),
                    'saving'          => __('Guardando...', 'aura-suite'),
                    'creatingUser'    => __('Creando usuario en WordPress...', 'aura-suite'),
                    'error'           => __('Ocurrió un error inesperado.', 'aura-suite'),
                    'noData'          => __('No se encontraron terceros registrados.', 'aura-suite'),
                ),
            ));
        }

        // Selector reutilizable y modal explorador para cualquier módulo que lo requiera
        $css_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/css/third-parties-directory.css');
        wp_enqueue_style('aura-third-parties-directory-css', AURA_PLUGIN_URL . 'assets/css/third-parties-directory.css', array(), $css_ver);

        wp_enqueue_script('aura-third-party-selector', AURA_PLUGIN_URL . 'assets/js/third-party-selector.js', array('jquery', 'jquery-ui-autocomplete'), AURA_VERSION, true);
        wp_localize_script('aura-third-party-selector', 'auraCounterpartiesData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('aura_search_counterparties_nonce'),
        ));
    }

    /**
     * Verificar permisos para acciones AJAX
     */
    private static function check_ajax_permissions($required_cap = 'aura_third_parties_view') {
        check_ajax_referer('aura_third_parties_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Sesión expirada.', 'aura-suite')), 401);
        }

        if (current_user_can('manage_options')) {
            return;
        }

        // Para consultas y vistas, permitir también a usuarios operativos de finanzas o inventario
        if ($required_cap === 'aura_third_parties_view') {
            if (current_user_can('aura_third_parties_view') ||
                current_user_can('aura_third_parties_create') ||
                current_user_can('aura_third_parties_edit') ||
                current_user_can('aura_third_parties_delete') ||
                current_user_can('aura_finance_create') ||
                current_user_can('aura_finance_view_all') ||
                current_user_can('aura_finance_view_own') ||
                current_user_can('aura_inventory_view_all') ||
                current_user_can('aura_inventory_checkout')) {
                return;
            }
        }

        // Para crear usuario WordPress desde Terceros
        if ($required_cap === 'aura_third_parties_create_wp_user') {
            if (current_user_can('create_users') ||
                current_user_can('aura_third_parties_create_wp_user') ||
                current_user_can('aura_admin_users_create')) {
                return;
            }
        }

        if (!current_user_can($required_cap)) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes para realizar esta acción.', 'aura-suite')), 403);
        }
    }

    /**
     * Obtener listado de terceros con filtros
     */
    public static function get_third_parties($include_inactive = false, $party_type = '', $search = '') {
        global $wpdb;

        $table = self::get_table_name();
        $users = $wpdb->users;

        $where = array();

        if (!$include_inactive) {
            $where[] = 'tp.is_active = 1';
        }

        if (!empty($party_type) && in_array($party_type, array('company', 'store', 'organization_foundation', 'person', 'religious', 'other'), true)) {
            $where[] = $wpdb->prepare('tp.party_type = %s', $party_type);
        }

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = $wpdb->prepare('(tp.full_name LIKE %s OR tp.commercial_name LIKE %s OR tp.document_id LIKE %s OR tp.email LIKE %s OR tp.phone LIKE %s)', $like, $like, $like, $like, $like);
        }

        $where_sql = !empty($where) ? implode(' AND ', $where) : '1=1';

        $rows = $wpdb->get_results(
            "SELECT tp.*,
                    u.user_login AS wp_user_login, u.user_email AS wp_user_email, u.display_name AS wp_user_display
             FROM {$table} tp
             LEFT JOIN {$users} u ON u.ID = tp.wp_user_id
             WHERE {$where_sql}
             ORDER BY tp.full_name ASC
             LIMIT 2000",
            ARRAY_A
        );

        if (!empty($rows)) {
            foreach ($rows as &$r) {
                $logo_id = (int) ($r['logo_id'] ?? 0);
                $r['logo_url'] = $logo_id ? (wp_get_attachment_image_url($logo_id, 'thumbnail') ?: wp_get_attachment_url($logo_id) ?: '') : '';
                if (!empty($r['wp_user_id']) && empty($r['wp_user_login'])) {
                    $r['wp_user_id'] = null;
                }
            }
            unset($r);
        }

        return $rows ?: array();
    }

    /**
     * Obtener estadísticas de KPIs
     */
    public static function get_kpis() {
        global $wpdb;
        $table = self::get_table_name();
        $counts = $wpdb->get_results(
            "SELECT party_type, COUNT(*) as count, is_active FROM {$table} GROUP BY party_type, is_active",
            ARRAY_A
        );

        $kpis = array(
            'total'                   => 0,
            'company'                 => 0,
            'store'                   => 0,
            'organization_foundation' => 0,
            'person'                  => 0,
            'religious'               => 0,
            'other'                   => 0,
            'inactive'                => 0,
        );

        if (!empty($counts)) {
            foreach ($counts as $c) {
                $qty = (int) $c['count'];
                $kpis['total'] += $qty;
                if ((int) $c['is_active'] === 0) {
                    $kpis['inactive'] += $qty;
                } else {
                    $pt = $c['party_type'] ?: 'company';
                    if (isset($kpis[$pt])) {
                        $kpis[$pt] += $qty;
                    }
                }
            }
        }

        return $kpis;
    }

    /**
     * Obtener un tercero por ID
     */
    public static function get_third_party_by_id($id) {
        $id = absint($id);
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $table = self::get_table_name();
        $users = $wpdb->users;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT tp.*,
                        u.user_login AS wp_user_login, u.user_email AS wp_user_email, u.display_name AS wp_user_display
                 FROM {$table} tp
                 LEFT JOIN {$users} u ON u.ID = tp.wp_user_id
                 WHERE tp.id = %d",
                $id
            ),
            ARRAY_A
        );

        if ($row) {
            $logo_id = (int) ($row['logo_id'] ?? 0);
            $row['logo_url'] = $logo_id ? (wp_get_attachment_image_url($logo_id, 'thumbnail') ?: wp_get_attachment_url($logo_id) ?: '') : '';
            if (!empty($row['wp_user_id']) && empty($row['wp_user_login'])) {
                $row['wp_user_id'] = null;
            }
        }

        return $row;
    }

    /**
     * Garantizar existencia de tercero (buscar o crear)
     */
    public static function ensure_third_party($full_name, $extra = array(), $created_by = 0) {
        $full_name = sanitize_text_field((string) $full_name);
        if ($full_name === '') {
            return 0;
        }

        global $wpdb;
        $table = self::get_table_name();

        $wp_user_id = absint($extra['wp_user_id'] ?? 0);
        if ($wp_user_id > 0) {
            $existing_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE wp_user_id = %d AND is_active = 1 LIMIT 1",
                $wp_user_id
            ));
            if ($existing_id > 0) {
                return $existing_id;
            }
        }

        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE LOWER(full_name) = LOWER(%s) AND is_active = 1 LIMIT 1",
            $full_name
        ));

        $party_type      = sanitize_key((string) ($extra['party_type'] ?? 'company'));
        if (!in_array($party_type, array('company', 'store', 'organization_foundation', 'person', 'religious', 'other'), true)) {
            $party_type = 'company';
        }
        $accounting_role = sanitize_key((string) ($extra['accounting_role'] ?? 'supplier'));
        $roles = self::get_accounting_roles();
        if (!isset($roles[$accounting_role])) {
            $accounting_role = 'supplier';
        }
        $commercial_name = sanitize_text_field((string) ($extra['commercial_name'] ?? ''));
        $document_id     = sanitize_text_field((string) ($extra['document_id'] ?? ''));
        $tax_id_type     = sanitize_text_field((string) ($extra['tax_id_type'] ?? 'NIT'));
        $phone           = sanitize_text_field((string) ($extra['phone'] ?? ''));
        $email           = sanitize_email((string) ($extra['email'] ?? ''));
        $website         = esc_url_raw((string) ($extra['website'] ?? ''));
        $address         = sanitize_textarea_field((string) ($extra['address'] ?? ''));
        $notes           = sanitize_textarea_field((string) ($extra['notes'] ?? ''));
        $logo_id         = absint($extra['logo_id'] ?? 0);

        if ($existing_id > 0) {
            $update_data = array();
            $update_formats = array();
            if ($wp_user_id > 0) {
                $update_data['wp_user_id'] = $wp_user_id;
                $update_formats[] = '%d';
            }
            if ($logo_id > 0) {
                $update_data['logo_id'] = $logo_id;
                $update_formats[] = '%d';
            }
            if ($commercial_name !== '') {
                $update_data['commercial_name'] = $commercial_name;
                $update_formats[] = '%s';
            }
            if ($party_type !== '') {
                $update_data['party_type'] = $party_type;
                $update_formats[] = '%s';
            }
            if ($accounting_role !== '') {
                $update_data['accounting_role'] = $accounting_role;
                $update_formats[] = '%s';
            }
            if ($document_id !== '') {
                $update_data['document_id'] = $document_id;
                $update_formats[] = '%s';
            }
            if ($tax_id_type !== '') {
                $update_data['tax_id_type'] = $tax_id_type;
                $update_formats[] = '%s';
            }
            if ($phone !== '') {
                $update_data['phone'] = $phone;
                $update_formats[] = '%s';
            }
            if ($email !== '') {
                $update_data['email'] = $email;
                $update_formats[] = '%s';
            }
            if ($website !== '') {
                $update_data['website'] = $website;
                $update_formats[] = '%s';
            }
            if ($address !== '') {
                $update_data['address'] = $address;
                $update_formats[] = '%s';
            }
            if ($logo_id > 0) {
                $update_data['logo_id'] = $logo_id;
                $update_formats[] = '%d';
            }
            if ($wp_user_id > 0) {
                $update_data['wp_user_id'] = $wp_user_id;
                $update_formats[] = '%d';
            }
            if ($notes !== '') {
                $update_data['notes'] = $notes;
                $update_formats[] = '%s';
            }
            if (!empty($update_data)) {
                $update_data['updated_at'] = current_time('mysql');
                $update_formats[] = '%s';
                $wpdb->update($table, $update_data, array('id' => $existing_id), $update_formats, array('%d'));
            }
            return $existing_id;
        }

        $created_by = absint($created_by);
        $inserted = $wpdb->insert(
            $table,
            array(
                'full_name'       => $full_name,
                'commercial_name' => $commercial_name ?: null,
                'party_type'      => $party_type,
                'accounting_role' => $accounting_role ?: 'supplier',
                'document_id'     => $document_id ?: null,
                'tax_id_type'     => $tax_id_type ?: 'NIT',
                'phone'           => $phone ?: null,
                'email'           => $email ?: null,
                'website'         => $website ?: null,
                'address'         => $address ?: null,
                'notes'           => $notes ?: null,
                'logo_id'         => $logo_id > 0 ? $logo_id : null,
                'is_active'       => 1,
                'wp_user_id'      => $wp_user_id > 0 ? $wp_user_id : null,
                'created_by'      => $created_by > 0 ? $created_by : get_current_user_id(),
                'created_at'      => current_time('mysql'),
                'updated_at'      => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s')
        );

        if (!$inserted) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Resumen 360° de la entidad
     */
    public static function get_summary_360($id) {
        $id = absint($id);
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $tp = self::get_third_party_by_id($id);
        if (!$tp) {
            return null;
        }

        $summary = array(
            'third_party' => $tp,
            'financial'   => array(
                'tx_count'     => 0,
                'total_in'     => 0.0,
                'total_out'    => 0.0,
                'recent_txs'   => array(),
                'reimbursements' => array('count' => 0, 'owed' => 0.0, 'paid' => 0.0),
            ),
            'inventory'   => array('loans_active' => 0, 'items' => array()),
            'library'     => array('loans_active' => 0, 'books' => array()),
            'vehicles'    => array('trips_count'  => 0, 'services' => array()),
        );

        // 1. Finanzas: Transacciones
        $tx_table = $wpdb->prefix . 'aura_finance_transactions';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tx_table}'") === $tx_table) {
            $tx_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) AS total_count,
                        SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) AS total_in,
                        SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) AS total_out
                 FROM {$tx_table}
                 WHERE third_party_id = %d AND deleted_at IS NULL",
                $id
            ), ARRAY_A);

            if ($tx_stats) {
                $summary['financial']['tx_count']  = (int) $tx_stats['total_count'];
                $summary['financial']['total_in']  = (float) ($tx_stats['total_in'] ?? 0);
                $summary['financial']['total_out'] = (float) ($tx_stats['total_out'] ?? 0);
            }

            $summary['financial']['recent_txs'] = $wpdb->get_results($wpdb->prepare(
                "SELECT id, transaction_date, transaction_type, amount, description, status
                 FROM {$tx_table}
                 WHERE third_party_id = %d AND deleted_at IS NULL
                 ORDER BY transaction_date DESC, id DESC
                 LIMIT 5",
                $id
            ), ARRAY_A) ?: array();
        }

        // 2. Finanzas: Reembolsos
        $reimb_table = $wpdb->prefix . 'aura_finance_reimbursements';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$reimb_table}'") === $reimb_table) {
            $reimb_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) AS total_count,
                        SUM(owed_amount) AS total_owed,
                        SUM(paid_amount) AS total_paid
                 FROM {$reimb_table}
                 WHERE counterparty_id = %d",
                $id
            ), ARRAY_A);

            if ($reimb_stats) {
                $summary['financial']['reimbursements']['count'] = (int) $reimb_stats['total_count'];
                $summary['financial']['reimbursements']['owed']  = (float) ($reimb_stats['total_owed'] ?? 0);
                $summary['financial']['reimbursements']['paid']  = (float) ($reimb_stats['total_paid'] ?? 0);
            }
        }

        // 3. Inventario: Préstamos de equipos
        $inv_table = $wpdb->prefix . 'aura_inventory_loans';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$inv_table}'") === $inv_table) {
            $inv_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$inv_table} WHERE borrower_id = %d AND status = 'active'",
                $id
            ));
            $summary['inventory']['loans_active'] = (int) $inv_count;
        }

        // 4. Biblioteca: Préstamos de libros
        $lib_table = $wpdb->prefix . 'aura_library_loans';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$lib_table}'") === $lib_table) {
            $lib_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$lib_table} WHERE (reader_id = %d OR borrower_id = %d) AND status = 'active'",
                $id, $id
            ));
            $summary['library']['loans_active'] = (int) $lib_count;
        }

        return $summary;
    }

    /**
     * AJAX: Listar terceros
     */
    public static function ajax_list() {
        self::check_ajax_permissions();

        $party_type = sanitize_key($_POST['party_type'] ?? '');
        $search     = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));

        $items = self::get_third_parties(true, $party_type, $search);

        // Estadísticas rápidas (KPIs)
        $kpis = self::get_kpis();

        wp_send_json_success(array(
            'items' => $items,
            'kpis'  => $kpis,
        ));
    }

    /**
     * AJAX: Buscar terceros (autocompletado simple)
     */
    public static function ajax_search() {
        self::check_ajax_permissions();

        $term = sanitize_text_field(wp_unslash($_GET['term'] ?? $_POST['term'] ?? ''));
        $rows = self::get_third_parties(false, '', $term);

        $type_labels = array(
            'company'                 => __('Empresa / Negocio', 'aura-suite'),
            'store'                   => __('Tienda / Comercio', 'aura-suite'),
            'organization_foundation' => __('Fundación / ONG', 'aura-suite'),
            'person'                  => __('Persona Natural', 'aura-suite'),
            'religious'               => __('Entidad Religiosa', 'aura-suite'),
            'other'                   => __('Otra Entidad', 'aura-suite'),
        );

        $type_icons = array(
            'company'                 => 'dashicons-building',
            'store'                   => 'dashicons-cart',
            'organization_foundation' => 'dashicons-heart',
            'person'                  => 'dashicons-businessman',
            'religious'               => 'dashicons-location-alt',
            'other'                   => 'dashicons-category',
        );

        $results = array();
        foreach ($rows as $r) {
            $ptype = $r['party_type'] ?: 'company';
            $display_name = !empty($r['commercial_name']) ? ($r['commercial_name'] . ' (' . $r['full_name'] . ')') : $r['full_name'];

            $results[] = array(
                'id'               => (int) $r['id'],
                'full_name'        => $r['full_name'],
                'commercial_name'  => $r['commercial_name'] ?: '',
                'display_name'     => $display_name,
                'party_type'       => $ptype,
                'party_type_label' => $type_labels[$ptype] ?? $type_labels['company'],
                'icon'             => $type_icons[$ptype] ?? 'dashicons-building',
                'document_id'      => $r['document_id'] ?: '',
                'tax_id_type'      => $r['tax_id_type'] ?: 'NIT',
                'phone'            => $r['phone'] ?: '',
                'email'            => $r['email'] ?: '',
                'logo_id'          => (int) ($r['logo_id'] ?? 0),
                'logo_url'         => $r['logo_url'] ?: '',
                'label'            => $display_name . ($r['document_id'] ? ' - ' . ($r['tax_id_type'] ?: 'Doc') . ': ' . $r['document_id'] : ''),
                'value'            => $r['commercial_name'] ?: $r['full_name'],
            );
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX: Búsqueda unificada transversal (Terceros y Usuarios WP) agrupada por rol contable
     */
    public static function ajax_search_counterparties() {
        if (isset($_REQUEST['nonce'])) {
            check_ajax_referer('aura_search_counterparties_nonce', 'nonce');
        }

        $term = sanitize_text_field(wp_unslash($_POST['term'] ?? $_GET['term'] ?? ''));
        if (strlen($term) < 1) {
            wp_send_json_success(array());
        }

        global $wpdb;
        $results = array();
        $roles = self::get_accounting_roles();

        // 1. Buscar en Terceros / Empresas
        $table = self::get_table_name();
        $like = '%' . $wpdb->esc_like($term) . '%';
        $tp_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, full_name, commercial_name, party_type, accounting_role, document_id, tax_id_type, phone, email, logo_id
             FROM {$table}
             WHERE is_active = 1
               AND (full_name LIKE %s OR commercial_name LIKE %s OR document_id LIKE %s OR email LIKE %s)
             ORDER BY accounting_role ASC, full_name ASC
             LIMIT 25",
            $like, $like, $like, $like
        ), ARRAY_A);

        $type_labels = array(
            'company'                 => __('Empresa', 'aura-suite'),
            'store'                   => __('Tienda', 'aura-suite'),
            'organization_foundation' => __('Fundación', 'aura-suite'),
            'person'                  => __('Persona Natural', 'aura-suite'),
            'religious'               => __('Entidad Religiosa', 'aura-suite'),
            'other'                   => __('Otra Entidad', 'aura-suite'),
        );

        $type_icons = array(
            'company'                 => 'dashicons-building',
            'store'                   => 'dashicons-cart',
            'organization_foundation' => 'dashicons-heart',
            'person'                  => 'dashicons-businessman',
            'religious'               => 'dashicons-location-alt',
            'other'                   => 'dashicons-category',
        );

        if (!empty($tp_rows)) {
            foreach ($tp_rows as $tp) {
                $logo_id = (int) ($tp['logo_id'] ?? 0);
                $logo_url = $logo_id ? (wp_get_attachment_image_url($logo_id, 'thumbnail') ?: wp_get_attachment_url($logo_id) ?: '') : '';
                $ptype = $tp['party_type'] ?: 'company';
                $arole = $tp['accounting_role'] ?: 'supplier';
                $role_info = $roles[$arole] ?? $roles['other'];

                $results[] = array(
                    'id'                    => 'tp_' . $tp['id'],
                    'third_party_id'        => (int) $tp['id'],
                    'type'                  => 'third_party',
                    'party_type'            => $ptype,
                    'party_type_label'      => $type_labels[$ptype] ?? $type_labels['company'],
                    'accounting_role'       => $arole,
                    'accounting_role_label' => $role_info['label'],
                    'category'              => $role_info['label'],
                    'category_order'        => (int) ($role_info['order'] ?? 99),
                    'category_badge'        => $role_info['badge'] ?? 'aura-pill--muted',
                    'name'                  => $tp['full_name'],
                    'commercial_name'       => $tp['commercial_name'] ?: '',
                    'document_id'           => $tp['document_id'] ?: '',
                    'tax_id_type'           => $tp['tax_id_type'] ?: 'NIT',
                    'email'                 => $tp['email'] ?: '',
                    'avatar_url'            => $logo_url,
                    'icon'                  => $type_icons[$ptype] ?? 'dashicons-building',
                    'label'                 => ($tp['commercial_name'] ?: $tp['full_name']) . ($tp['document_id'] ? ' (' . ($tp['tax_id_type'] ?: 'NIT') . ': ' . $tp['document_id'] . ')' : '') . ' — [' . $role_info['label'] . ']',
                    'value'                 => $tp['commercial_name'] ?: $tp['full_name'],
                );
            }
        }

        // 2. Buscar en Usuarios WP (Clasificados como Empleados / Usuarios Sistema)
        $user_query = new WP_User_Query(array(
            'search'         => '*' . $term . '*',
            'search_columns' => array('user_login', 'user_nicename', 'user_email', 'display_name'),
            'number'         => 8,
        ));
        $users = $user_query->get_results();

        if (!empty($users)) {
            $user_role_info = $roles['employee'] ?? array('label' => __('Empleados / Colaboradores', 'aura-suite'), 'order' => 3, 'badge' => 'aura-pill--info');
            foreach ($users as $user) {
                $results[] = array(
                    'id'                    => 'user_' . $user->ID,
                    'user_id'               => $user->ID,
                    'type'                  => 'wp_user',
                    'party_type'            => 'user',
                    'party_type_label'      => __('Usuario Sistema', 'aura-suite'),
                    'accounting_role'       => 'employee',
                    'accounting_role_label' => $user_role_info['label'],
                    'category'              => $user_role_info['label'],
                    'category_order'        => (int) ($user_role_info['order'] ?? 3),
                    'category_badge'        => $user_role_info['badge'],
                    'name'                  => $user->display_name,
                    'login'                 => $user->user_login,
                    'email'                 => $user->user_email,
                    'avatar_url'            => get_avatar_url($user->ID, array('size' => 48)),
                    'icon'                  => 'dashicons-admin-users',
                    'label'                 => $user->display_name . ' (@' . $user->user_login . ') — [' . $user_role_info['label'] . ']',
                    'value'                 => $user->display_name,
                );
            }
        }

        // Ordenar por orden de categoría contable y luego por nombre
        usort($results, function($a, $b) {
            if (($a['category_order'] ?? 99) === ($b['category_order'] ?? 99)) {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            }
            return ($a['category_order'] ?? 99) <=> ($b['category_order'] ?? 99);
        });

        wp_send_json_success($results);
    }

    /**
     * AJAX: Catálogo completo de terceros clasificados por rol contable (para el modal explorador)
     */
    public static function ajax_catalog_by_role() {
        if (isset($_REQUEST['nonce'])) {
            check_ajax_referer('aura_search_counterparties_nonce', 'nonce');
        }

        global $wpdb;
        $table = self::get_table_name();
        $rows = $wpdb->get_results(
            "SELECT id, full_name, commercial_name, party_type, accounting_role, document_id, tax_id_type, phone, email, logo_id
             FROM {$table}
             WHERE is_active = 1
             ORDER BY full_name ASC",
            ARRAY_A
        );

        $roles = self::get_accounting_roles();
        $catalog = array(
            'all'           => array(),
            'supplier'      => array(),
            'customer'      => array(),
            'employee'      => array(),
            'bank_creditor' => array(),
            'tax_authority' => array(),
            'other'         => array(),
        );

        $party_type_labels = array(
            'company'                 => __('Empresa / Sociedad', 'aura-suite'),
            'store'                   => __('Comercio / Tienda', 'aura-suite'),
            'person'                  => __('Persona Natural', 'aura-suite'),
            'organization_foundation' => __('Organización / Fundación', 'aura-suite'),
            'religious'               => __('Entidad Religiosa', 'aura-suite'),
            'other'                   => __('Otra Entidad', 'aura-suite'),
            'user'                    => __('Usuario Sistema', 'aura-suite'),
        );

        $type_counts = array(
            'all'                     => 0,
            'company'                 => 0,
            'store'                   => 0,
            'organization_foundation' => 0,
            'person'                  => 0,
            'religious'               => 0,
            'other'                   => 0,
            'user'                    => 0,
        );

        $party_type_icons = array(
            'company'                 => 'dashicons-building',
            'store'                   => 'dashicons-cart',
            'organization_foundation' => 'dashicons-heart',
            'person'                  => 'dashicons-businessman',
            'religious'               => 'dashicons-location-alt',
            'other'                   => 'dashicons-category',
            'user'                    => 'dashicons-admin-users',
        );

        if (!empty($rows)) {
            foreach ($rows as $r) {
                $role = $r['accounting_role'] ?: 'supplier';
                if (!isset($catalog[$role])) {
                    $role = 'other';
                }

                $ptype = $r['party_type'] ?: 'company';
                if (!isset($type_counts[$ptype])) {
                    $type_counts[$ptype] = 0;
                }
                $type_counts[$ptype]++;

                $logo_id = (int) ($r['logo_id'] ?? 0);
                $logo_url = $logo_id ? (wp_get_attachment_image_url($logo_id, 'thumbnail') ?: wp_get_attachment_url($logo_id) ?: '') : '';
                
                $item = array(
                    'id'                    => (int) $r['id'],
                    'type'                  => 'third_party',
                    'is_wp_user'            => false,
                    'name'                  => $r['full_name'],
                    'commercial_name'       => $r['commercial_name'] ?: '',
                    'display_name'          => $r['commercial_name'] ?: $r['full_name'],
                    'party_type'            => $ptype,
                    'party_type_label'      => $party_type_labels[$ptype] ?? ucfirst($ptype),
                    'party_type_icon'       => $party_type_icons[$ptype] ?? 'dashicons-building',
                    'accounting_role'       => $role,
                    'accounting_role_label' => $roles[$role]['label'] ?? $roles['other']['label'],
                    'badge_class'           => $roles[$role]['badge'] ?? 'aura-pill--muted',
                    'document_id'           => $r['document_id'] ?: '',
                    'tax_id_type'           => $r['tax_id_type'] ?: 'NIT',
                    'email'                 => $r['email'] ?: '',
                    'phone'                 => $r['phone'] ?: '',
                    'logo_url'              => $logo_url,
                );

                $catalog['all'][] = $item;
                $catalog[$role][] = $item;
            }
        }

        // 2. Incluir Usuarios WordPress con sus roles traducidos
        $all_wp_roles = wp_roles()->get_names();
        $wp_users = get_users(array(
            'number' => 250,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ));
        
        $user_roles_list = array();

        if (!empty($wp_users)) {
            foreach ($wp_users as $u) {
                $u_roles = (array) ($u->roles ?? array());
                $primary_role_slug = !empty($u_roles) ? $u_roles[0] : 'subscriber';
                $primary_role_label = translate_user_role($all_wp_roles[$primary_role_slug] ?? ucfirst($primary_role_slug));

                if (!isset($user_roles_list[$primary_role_slug])) {
                    $user_roles_list[$primary_role_slug] = array(
                        'slug'  => $primary_role_slug,
                        'label' => $primary_role_label,
                        'count' => 0
                    );
                }
                $user_roles_list[$primary_role_slug]['count']++;
                $type_counts['user']++;

                $user_item = array(
                    'id'                    => (int) $u->ID,
                    'user_id'               => (int) $u->ID,
                    'is_wp_user'            => true,
                    'type'                  => 'wp_user',
                    'name'                  => $u->display_name,
                    'login'                 => $u->user_login,
                    'commercial_name'       => '',
                    'display_name'          => $u->display_name . ' (@' . $u->user_login . ')',
                    'party_type'            => 'user',
                    'party_type_label'      => __('Usuario Sistema', 'aura-suite'),
                    'party_type_icon'       => 'dashicons-admin-users',
                    'accounting_role'       => 'employee',
                    'accounting_role_label' => __('Usuario Sistema', 'aura-suite'),
                    'user_role_slug'        => $primary_role_slug,
                    'user_role_label'       => $primary_role_label,
                    'badge_class'           => 'aura-pill--info',
                    'document_id'           => '@' . $u->user_login,
                    'tax_id_type'           => 'User',
                    'email'                 => $u->user_email ?: '',
                    'phone'                 => '',
                    'logo_url'              => get_avatar_url($u->ID, array('size' => 64)),
                );

                $catalog['all'][] = $user_item;
                $catalog['employee'][] = $user_item;
            }
        }

        $type_counts['all'] = count($catalog['all']);

        wp_send_json_success(array(
            'roles'            => $roles,
            'party_types'      => $party_type_labels,
            'user_roles'       => array_values($user_roles_list),
            'catalog'          => $catalog,
            'counts_by_type'   => $type_counts,
            'counts_by_role'   => array(
                'all'           => count($catalog['all']),
                'supplier'      => count($catalog['supplier']),
                'customer'      => count($catalog['customer']),
                'employee'      => count($catalog['employee']),
                'bank_creditor' => count($catalog['bank_creditor']),
                'tax_authority' => count($catalog['tax_authority']),
                'other'         => count($catalog['other']),
            ),
            'counts'           => array(
                'all'           => count($catalog['all']),
                'supplier'      => count($catalog['supplier']),
                'customer'      => count($catalog['customer']),
                'employee'      => count($catalog['employee']),
                'bank_creditor' => count($catalog['bank_creditor']),
                'tax_authority' => count($catalog['tax_authority']),
                'other'         => count($catalog['other']),
            )
        ));
    }

    /**
     * AJAX: Crear tercero
     */
    public static function ajax_create() {
        self::check_ajax_permissions('aura_third_parties_create');

        $full_name       = sanitize_text_field(wp_unslash($_POST['full_name'] ?? ''));
        $commercial_name = sanitize_text_field(wp_unslash($_POST['commercial_name'] ?? ''));
        $party_type      = sanitize_key(wp_unslash($_POST['party_type'] ?? 'company'));
        $accounting_role = sanitize_key(wp_unslash($_POST['accounting_role'] ?? 'supplier'));
        $document_id     = sanitize_text_field(wp_unslash($_POST['document_id'] ?? ''));
        $tax_id_type     = sanitize_text_field(wp_unslash($_POST['tax_id_type'] ?? 'NIT'));
        $phone           = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $email           = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $website         = esc_url_raw(wp_unslash($_POST['website'] ?? ''));
        $address         = sanitize_textarea_field(wp_unslash($_POST['address'] ?? ''));
        $notes           = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        $logo_id         = absint($_POST['logo_id'] ?? 0);

        if (!in_array($party_type, array('company', 'store', 'organization_foundation', 'person', 'religious', 'other'), true)) {
            $party_type = 'company';
        }

        $roles = self::get_accounting_roles();
        if (!isset($roles[$accounting_role])) {
            $accounting_role = 'supplier';
        }

        if ($full_name === '') {
            wp_send_json_error(array('message' => __('Debes indicar el nombre o razón social.', 'aura-suite')));
        }

        if ($email !== '' && !is_email($email)) {
            wp_send_json_error(array('message' => __('El correo no es válido.', 'aura-suite')));
        }

        $id = self::ensure_third_party(
            $full_name,
            array(
                'commercial_name' => $commercial_name,
                'party_type'      => $party_type,
                'accounting_role' => $accounting_role,
                'document_id'     => $document_id,
                'tax_id_type'     => $tax_id_type,
                'phone'           => $phone,
                'email'           => $email,
                'website'         => $website,
                'address'         => $address,
                'notes'           => $notes,
                'logo_id'         => $logo_id > 0 ? $logo_id : null,
            ),
            get_current_user_id()
        );

        if ($id <= 0) {
            wp_send_json_error(array('message' => __('No se pudo registrar el tercero.', 'aura-suite')));
        }

        $item = self::get_third_party_by_id($id);

        wp_send_json_success(array(
            'id'      => $id,
            'item'    => $item,
            'message' => __('Tercero registrado correctamente.', 'aura-suite'),
        ));
    }

    /**
     * AJAX: Actualizar tercero
     */
    public static function ajax_update() {
        self::check_ajax_permissions('aura_third_parties_edit');

        global $wpdb;
        $table = self::get_table_name();

        $id              = absint($_POST['id'] ?? 0);
        $full_name       = sanitize_text_field(wp_unslash($_POST['full_name'] ?? ''));
        $commercial_name = sanitize_text_field(wp_unslash($_POST['commercial_name'] ?? ''));
        $party_type      = sanitize_key(wp_unslash($_POST['party_type'] ?? 'company'));
        $accounting_role = sanitize_key(wp_unslash($_POST['accounting_role'] ?? 'supplier'));
        $document_id     = sanitize_text_field(wp_unslash($_POST['document_id'] ?? ''));
        $tax_id_type     = sanitize_text_field(wp_unslash($_POST['tax_id_type'] ?? 'NIT'));
        $phone           = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $email           = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $website         = esc_url_raw(wp_unslash($_POST['website'] ?? ''));
        $address         = sanitize_textarea_field(wp_unslash($_POST['address'] ?? ''));
        $notes           = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        $logo_id         = absint($_POST['logo_id'] ?? 0);

        if (!in_array($party_type, array('company', 'store', 'organization_foundation', 'person', 'religious', 'other'), true)) {
            $party_type = 'company';
        }

        $roles = self::get_accounting_roles();
        if (!isset($roles[$accounting_role])) {
            $accounting_role = 'supplier';
        }

        if ($id <= 0) {
            wp_send_json_error(array('message' => __('Tercero inválido.', 'aura-suite')));
        }

        if ($full_name === '') {
            wp_send_json_error(array('message' => __('Debes indicar el nombre o razón social.', 'aura-suite')));
        }

        if ($email !== '' && !is_email($email)) {
            wp_send_json_error(array('message' => __('El correo no es válido.', 'aura-suite')));
        }

        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id = %d", $id));
        if ($exists <= 0) {
            wp_send_json_error(array('message' => __('El tercero no existe.', 'aura-suite')));
        }

        $ok = $wpdb->update(
            $table,
            array(
                'full_name'       => $full_name,
                'commercial_name' => $commercial_name ?: null,
                'party_type'      => $party_type,
                'accounting_role' => $accounting_role,
                'document_id'     => $document_id ?: null,
                'tax_id_type'     => $tax_id_type ?: 'NIT',
                'phone'           => $phone ?: null,
                'email'           => $email ?: null,
                'website'         => $website ?: null,
                'address'         => $address ?: null,
                'notes'           => $notes ?: null,
                'logo_id'         => $logo_id > 0 ? $logo_id : null,
                'updated_at'      => current_time('mysql'),
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );

        if ($ok === false) {
            wp_send_json_error(array('message' => __('No se pudo actualizar el tercero.', 'aura-suite')));
        }

        // Si el tercero tiene un usuario de WordPress vinculado, sincronizar su avatar
        $linked_wp_user_id = (int) $wpdb->get_var($wpdb->prepare("SELECT wp_user_id FROM {$table} WHERE id = %d", $id));
        if ($linked_wp_user_id > 0 && $logo_id > 0) {
            self::sync_avatar_to_wp_user($linked_wp_user_id, $logo_id);
        }

        $item = self::get_third_party_by_id($id);

        wp_send_json_success(array(
            'id'      => $id,
            'item'    => $item,
            'message' => __('Tercero actualizado correctamente.', 'aura-suite'),
        ));
    }

    /**
     * AJAX: Cambiar estado (activar / desactivar)
     */
    public static function ajax_toggle() {
        self::check_ajax_permissions('aura_third_parties_delete');

        global $wpdb;
        $table = self::get_table_name();
        $id = absint($_POST['id'] ?? 0);

        if ($id <= 0) {
            wp_send_json_error(array('message' => __('ID inválido.', 'aura-suite')));
        }

        $current = (int) $wpdb->get_var($wpdb->prepare("SELECT is_active FROM {$table} WHERE id = %d", $id));
        $new_status = $current === 1 ? 0 : 1;

        $wpdb->update(
            $table,
            array('is_active' => $new_status, 'updated_at' => current_time('mysql')),
            array('id' => $id),
            array('%d', '%s'),
            array('%d')
        );

        wp_send_json_success(array(
            'id'        => $id,
            'is_active' => $new_status,
            'message'   => $new_status === 1 ? __('Tercero reactivado.', 'aura-suite') : __('Tercero desactivado.', 'aura-suite'),
        ));
    }

    /**
     * AJAX: Eliminar tercero definitivamente del sistema y de la base de datos (Hard Delete)
     */
    public static function ajax_delete() {
        self::check_ajax_permissions('aura_third_parties_delete');

        if (!current_user_can('manage_options') && !current_user_can('aura_third_parties_delete')) {
            wp_send_json_error(array('message' => __('No tienes permiso para eliminar terceros.', 'aura-suite')));
        }

        global $wpdb;
        $table = self::get_table_name();
        $id = absint($_POST['id'] ?? 0);

        if ($id <= 0) {
            wp_send_json_error(array('message' => __('ID de tercero no válido.', 'aura-suite')));
        }

        $tp = self::get_third_party_by_id($id);
        if (!$tp) {
            wp_send_json_error(array('message' => __('El tercero no existe en la base de datos o ya fue eliminado.', 'aura-suite')));
        }

        $tp_name = !empty($tp['commercial_name']) ? $tp['commercial_name'] : $tp['full_name'];

        // 1. Limpiar referencias foráneas en transacciones financieras preservando el nombre de contraparte
        $tx_table = $wpdb->prefix . 'aura_finance_transactions';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tx_table}'") === $tx_table) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$tx_table} SET recipient_payer = %s WHERE third_party_id = %d AND (recipient_payer IS NULL OR recipient_payer = '')",
                $tp_name,
                $id
            ));
            $wpdb->query($wpdb->prepare(
                "UPDATE {$tx_table} SET third_party_id = NULL WHERE third_party_id = %d",
                $id
            ));
        }

        // 2. Limpiar referencias en cuentas financieras
        $acc_table = $wpdb->prefix . 'aura_finance_accounts';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$acc_table}'") === $acc_table) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$acc_table} SET third_party_id = NULL WHERE third_party_id = %d",
                $id
            ));
        }

        // 3. Limpiar referencias en préstamos de inventario y biblioteca si las tablas existen
        $inv_loans = $wpdb->prefix . 'aura_inventory_loans';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$inv_loans}'") === $inv_loans) {
            $wpdb->query($wpdb->prepare("UPDATE {$inv_loans} SET third_party_id = NULL WHERE third_party_id = %d", $id));
        }
        $lib_loans = $wpdb->prefix . 'aura_library_loans';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$lib_loans}'") === $lib_loans) {
            $wpdb->query($wpdb->prepare("UPDATE {$lib_loans} SET third_party_id = NULL WHERE third_party_id = %d", $id));
        }

        // 4. Desvincular cualquier usuario de WordPress
        delete_metadata('user', 0, 'aura_third_party_id', $id, true);
        if (!empty($tp['wp_user_id'])) {
            delete_user_meta((int) $tp['wp_user_id'], 'aura_third_party_id');
        }

        // 5. Eliminar definitivamente el tercero de la tabla maestra
        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            wp_send_json_error(array('message' => __('Error al eliminar el registro de la base de datos.', 'aura-suite')));
        }

        wp_send_json_success(array(
            'id'      => $id,
            'message' => sprintf(__('Tercero "%s" (#%d) eliminado definitivamente de la base de datos sin dejar rastro.', 'aura-suite'), $tp_name, $id),
        ));
    }

    /**
     * AJAX: Obtener ficha 360°
     */
    public static function ajax_summary_360() {
        self::check_ajax_permissions('aura_third_parties_view');

        $id = absint($_POST['id'] ?? 0);
        $data = self::get_summary_360($id);

        if (!$data) {
            wp_send_json_error(array('message' => __('Tercero no encontrado.', 'aura-suite')));
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX: Crear o asociar un usuario de WordPress con rol Suscriptor desde un Tercero
     */
    public static function ajax_create_wp_user() {
        self::check_ajax_permissions('aura_third_parties_create_wp_user');

        global $wpdb;
        $table = self::get_table_name();

        $id = absint($_POST['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(array('message' => __('ID de tercero no válido.', 'aura-suite')));
        }

        $tp = self::get_third_party_by_id($id);
        if (!$tp) {
            wp_send_json_error(array('message' => __('Tercero no encontrado en la base de datos.', 'aura-suite')));
        }

        // Si ya tiene wp_user_id vinculado y el usuario existe en WP
        if (!empty($tp['wp_user_id'])) {
            $existing_linked = get_user_by('id', (int) $tp['wp_user_id']);
            if ($existing_linked) {
                if (!empty($tp['logo_id'])) {
                    self::sync_avatar_to_wp_user($existing_linked->ID, $tp['logo_id']);
                }
                wp_send_json_success(array(
                    'message'        => sprintf(__('Este tercero ya está vinculado al usuario @%s (%s).', 'aura-suite'), $existing_linked->user_login, $existing_linked->user_email),
                    'user_id'        => (int) $existing_linked->ID,
                    'user_login'     => $existing_linked->user_login,
                    'user_email'     => $existing_linked->user_email,
                    'edit_url'       => admin_url('user-edit.php?user_id=' . $existing_linked->ID),
                    'already_linked' => true,
                ));
            } else {
                // Usuario de WordPress ya no existe en el sistema: limpiar referencia huérfana
                $wpdb->update($table, array('wp_user_id' => null), array('id' => $id));
                $tp['wp_user_id'] = null;
            }
        }

        // Obtener y validar correo electrónico
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        if (empty($email)) {
            $email = sanitize_email($tp['email'] ?? '');
        }

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array(
                'message'     => __('Se requiere un correo electrónico válido para crear o vincular una cuenta de WordPress.', 'aura-suite'),
                'needs_email' => true,
            ));
        }

        // 1. Verificar si ya existe un usuario de WordPress con ese correo
        $existing_user = get_user_by('email', $email);
        if ($existing_user) {
            // Vincular el usuario existente al tercero
            $wpdb->update(
                $table,
                array(
                    'wp_user_id' => (int) $existing_user->ID,
                    'email'      => $email,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $id),
                array('%d', '%s', '%s'),
                array('%d')
            );

            update_user_meta($existing_user->ID, 'aura_third_party_id', $id);
            if (!empty($tp['logo_id'])) {
                self::sync_avatar_to_wp_user($existing_user->ID, $tp['logo_id']);
            }

            wp_send_json_success(array(
                'message'         => sprintf(__('Se vinculó exitosamente el usuario existente de WordPress (@%s) a este tercero.', 'aura-suite'), $existing_user->user_login),
                'user_id'         => (int) $existing_user->ID,
                'user_login'      => $existing_user->user_login,
                'user_email'      => $existing_user->user_email,
                'edit_url'        => admin_url('user-edit.php?user_id=' . $existing_user->ID),
                'linked_existing' => true,
            ));
        }

        // 2. Generar nombre de usuario (user_login)
        $custom_login = sanitize_user(wp_unslash($_POST['user_login'] ?? ''), true);
        if (!empty($custom_login)) {
            $username = $custom_login;
        } else {
            // Generar a partir del email o nombre
            $base_username = strtolower(sanitize_user(current(explode('@', $email)), true));
            if (empty($base_username) || strlen($base_username) < 3) {
                $base_username = strtolower(sanitize_user(str_replace(' ', '', $tp['full_name']), true));
            }
            if (empty($base_username)) {
                $base_username = 'usuario';
            }
            $username = $base_username;
        }

        // Garantizar unicidad de username
        if (username_exists($username)) {
            $suffix = 1;
            while (username_exists($username . $suffix)) {
                $suffix++;
            }
            $username = $username . $suffix;
        }

        // Separar nombres para el perfil
        $first_name = '';
        $last_name  = '';
        $display_name = !empty($tp['commercial_name']) ? $tp['commercial_name'] : $tp['full_name'];

        if ($tp['party_type'] === 'person') {
            $name_parts = explode(' ', trim($tp['full_name']));
            if (count($name_parts) >= 2) {
                $first_name = array_shift($name_parts);
                $last_name  = implode(' ', $name_parts);
            } else {
                $first_name = $tp['full_name'];
            }
        } else {
            $first_name = $display_name;
        }

        // Contraseña aleatoria segura
        $password = wp_generate_password(16, true, false);

        // Crear usuario en WordPress con rol Suscriptor
        $user_data = array(
            'user_login'   => $username,
            'user_pass'    => $password,
            'user_email'   => $email,
            'display_name' => $display_name,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'role'         => 'subscriber',
        );

        $new_user_id = wp_insert_user($user_data);

        if (is_wp_error($new_user_id)) {
            wp_send_json_error(array(
                'message' => sprintf(__('Error al crear el usuario en WordPress: %s', 'aura-suite'), $new_user_id->get_error_message()),
            ));
        }

        // Metadatos adicionales
        update_user_meta($new_user_id, 'aura_third_party_id', $id);
        if (!empty($tp['phone'])) {
            update_user_meta($new_user_id, 'aura_phone', $tp['phone']);
            update_user_meta($new_user_id, 'billing_phone', $tp['phone']);
        }
        if (!empty($tp['address'])) {
            update_user_meta($new_user_id, 'billing_address_1', $tp['address']);
        }
        if (!empty($tp['logo_id'])) {
            self::sync_avatar_to_wp_user($new_user_id, $tp['logo_id']);
        }

        // Actualizar la fila en la tabla de terceros con el nuevo wp_user_id y email
        $wpdb->update(
            $table,
            array(
                'wp_user_id' => $new_user_id,
                'email'      => $email,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $id),
            array('%d', '%s', '%s'),
            array('%d')
        );

        // Opcional: Notificación por correo a través de WordPress
        $send_notification = !empty($_POST['send_notification']);
        if ($send_notification) {
            wp_new_user_notification($new_user_id, null, 'both');
        }

        wp_send_json_success(array(
            'message'     => sprintf(__('¡Usuario de WordPress creado exitosamente con rol Suscriptor! (@%s)', 'aura-suite'), $username),
            'user_id'     => $new_user_id,
            'user_login'  => $username,
            'user_email'  => $email,
            'edit_url'    => admin_url('user-edit.php?user_id=' . $new_user_id),
            'created_new' => true,
        ));
    }

    /**
     * Sincronizar logotipo/foto del tercero como Avatar de WordPress del usuario
     */
    public static function sync_avatar_to_wp_user($wp_user_id, $logo_id) {
        $wp_user_id = absint($wp_user_id);
        $logo_id    = absint($logo_id);

        if ($wp_user_id <= 0 || $logo_id <= 0) {
            return false;
        }

        $full_url = wp_get_attachment_url($logo_id);
        if (!$full_url) {
            return false;
        }

        // 1. Integración con Simple Local Avatars (si el plugin está activo)
        if (class_exists('Simple_Local_Avatars')) {
            try {
                $sla = new \Simple_Local_Avatars();
                if (method_exists($sla, 'assign_new_user_avatar')) {
                    $sla->assign_new_user_avatar($logo_id, $wp_user_id);
                }
            } catch (\Throwable $t) {
                // Silencioso si falla
            }
        }

        // 2. Metadatos estándar y compatibles de Simple Local Avatars
        $sla_meta = array(
            'media_id' => $logo_id,
            'full'     => $full_url,
            'blog_id'  => get_current_blog_id(),
        );
        update_user_meta($wp_user_id, 'simple_local_avatar', $sla_meta);
        update_user_meta($wp_user_id, 'simple_local_avatar_rating', 'G');

        // 3. Metadatos compatibles con WP User Avatar / One User Avatar / temas comunes
        update_user_meta($wp_user_id, 'wp_user_avatar', $logo_id);
        update_user_meta($wp_user_id, 'profile_photo_id', $logo_id);

        // 4. Metadatos propios de Aura Suite
        update_user_meta($wp_user_id, 'aura_avatar_id', $logo_id);
        update_user_meta($wp_user_id, 'aura_avatar_url', $full_url);

        // Disparar hooks de compatibilidad
        do_action('simple_local_avatar_updated', $wp_user_id);

        return true;
    }

    /**
     * Filtro universal pre_get_avatar_data para resolver el avatar de WP desde el logotipo/foto del tercero
     */
    public static function filter_pre_get_avatar_data($args, $id_or_email) {
        if (!empty($args['url']) && empty($args['force_default'])) {
            return $args;
        }

        $user_id = 0;
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = (int) $user->ID;
            }
        } elseif (is_object($id_or_email)) {
            if (!empty($id_or_email->user_id)) {
                $user_id = (int) $id_or_email->user_id;
            } elseif ($id_or_email instanceof \WP_User) {
                $user_id = (int) $id_or_email->ID;
            }
        }

        if ($user_id <= 0) {
            return $args;
        }

        // Buscar logo_id en metadatos del usuario
        $logo_id = (int) get_user_meta($user_id, 'aura_avatar_id', true);
        if ($logo_id <= 0) {
            $logo_id = (int) get_user_meta($user_id, 'wp_user_avatar', true);
        }
        if ($logo_id <= 0) {
            $sla_meta = get_user_meta($user_id, 'simple_local_avatar', true);
            if (is_array($sla_meta) && !empty($sla_meta['media_id'])) {
                $logo_id = (int) $sla_meta['media_id'];
            }
        }
        if ($logo_id <= 0) {
            // Verificar si tiene un tercero vinculado en la base de datos
            global $wpdb;
            $table = self::get_table_name();
            $logo_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT logo_id FROM {$table} WHERE wp_user_id = %d AND logo_id IS NOT NULL AND logo_id > 0 LIMIT 1",
                $user_id
            ));
        }

        if ($logo_id > 0) {
            $size = !empty($args['size']) ? (int) $args['size'] : 96;
            $image_src = wp_get_attachment_image_src($logo_id, array($size, $size));
            if ($image_src && !empty($image_src[0])) {
                $args['url'] = $image_src[0];
                $args['found_avatar'] = true;
            } else {
                $full_url = wp_get_attachment_url($logo_id);
                if ($full_url) {
                    $args['url'] = $full_url;
                    $args['found_avatar'] = true;
                }
            }
        }

        return $args;
    }

    /**
     * Renderizar la página del Directorio Global en WP Admin
     */
    public static function render_admin_page() {
        $template = AURA_PLUGIN_DIR . 'templates/common/third-parties-page.php';
        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="wrap"><h1>' . esc_html__('Directorio de Terceros', 'aura-suite') . '</h1><p>' . esc_html__('Plantilla no encontrada.', 'aura-suite') . '</p></div>';
        }
    }
}
