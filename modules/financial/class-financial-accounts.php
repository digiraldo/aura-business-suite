<?php
/**
 * Cuentas Financieras (Bancos)
 *
 * Fase 0 + Fase 1:
 * - Migraciones base (cuentas, movimientos, rendiciones, reembolsos)
 * - Submenu Bancos
 * - CRUD base de cuentas por AJAX
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.7.8
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aura_Financial_Accounts {

    const DB_VERSION_OPTION = 'aura_finance_accounts_db_version';
    const DB_VERSION = '1.8.1';
    const BUDGET_IMPORT_MAX_ROWS = 300;
    const BUDGET_IMPORT_TRANSIENT_PREFIX = 'aura_budget_import_';
    const PETTY_CASH_DEFAULT_DUE_DAYS = 5;
    const PETTY_CASH_OVERDUE_CRON_HOOK = 'aura_finance_petty_cash_overdue_scan';
    const USD_LEDGER_MIGRATION_OPTION = 'aura_finance_usd_ledger_migration_v1';

    public static function init() {
        add_action('admin_init', array(__CLASS__, 'maybe_install'));
        add_action('admin_menu', array(__CLASS__, 'add_menu'), 20);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action(self::PETTY_CASH_OVERDUE_CRON_HOOK, array(__CLASS__, 'scan_overdue_petty_cash_settlements'));

        add_action('wp_ajax_aura_finance_accounts_list', array(__CLASS__, 'ajax_list_accounts'));
        add_action('wp_ajax_aura_finance_accounts_save', array(__CLASS__, 'ajax_save_account'));
        add_action('wp_ajax_aura_finance_accounts_delete', array(__CLASS__, 'ajax_delete_account'));
        add_action('wp_ajax_aura_finance_petty_cash_list', array(__CLASS__, 'ajax_list_petty_cash_settlements'));
        add_action('wp_ajax_aura_finance_petty_cash_create', array(__CLASS__, 'ajax_create_petty_cash_settlement'));
        add_action('wp_ajax_aura_finance_petty_cash_delete', array(__CLASS__, 'ajax_delete_petty_cash_settlement'));
        add_action('wp_ajax_aura_finance_petty_cash_submit', array(__CLASS__, 'ajax_submit_petty_cash_settlement'));
        add_action('wp_ajax_aura_finance_petty_cash_status', array(__CLASS__, 'ajax_update_petty_cash_status'));
        add_action('wp_ajax_aura_finance_reimbursements_list', array(__CLASS__, 'ajax_list_reimbursements'));
        add_action('wp_ajax_aura_finance_reimbursements_create', array(__CLASS__, 'ajax_create_reimbursement'));
        add_action('wp_ajax_aura_finance_reimbursements_pay', array(__CLASS__, 'ajax_pay_reimbursement'));
        add_action('wp_ajax_aura_finance_reimbursements_delete', array(__CLASS__, 'ajax_delete_reimbursement'));
        add_action('wp_ajax_aura_finance_third_parties_list', array(__CLASS__, 'ajax_list_third_parties'));
        add_action('wp_ajax_aura_finance_third_parties_search', array(__CLASS__, 'ajax_search_third_parties'));
        add_action('wp_ajax_aura_finance_third_parties_create', array(__CLASS__, 'ajax_create_third_party'));
        add_action('wp_ajax_aura_finance_third_parties_update', array(__CLASS__, 'ajax_update_third_party'));
        add_action('wp_ajax_aura_finance_third_parties_toggle', array(__CLASS__, 'ajax_toggle_third_party'));
        add_action('wp_ajax_aura_finance_third_parties_convert_user', array(__CLASS__, 'ajax_convert_third_party_to_user'));
        add_action('wp_ajax_aura_finance_accounts_report', array(__CLASS__, 'ajax_get_accounts_report'));
        add_action('wp_ajax_aura_finance_budget_get', array(__CLASS__, 'ajax_get_budget'));
        add_action('wp_ajax_aura_finance_budget_save', array(__CLASS__, 'ajax_save_budget'));
        add_action('wp_ajax_aura_finance_budget_delete', array(__CLASS__, 'ajax_delete_budget'));
        add_action('wp_ajax_aura_finance_budget_copy', array(__CLASS__, 'ajax_copy_budget'));
        add_action('wp_ajax_aura_finance_budget_import', array(__CLASS__, 'ajax_import_budget'));
        add_action('wp_ajax_aura_finance_budget_template', array(__CLASS__, 'ajax_download_budget_template'));
        add_action('wp_ajax_aura_finance_budget_export_all', array(__CLASS__, 'ajax_export_all_budgets'));
        add_action('wp_ajax_aura_finance_budget_upload_preview', array(__CLASS__, 'ajax_budget_upload_preview'));
        add_action('wp_ajax_aura_finance_budget_validate_preview', array(__CLASS__, 'ajax_budget_validate_preview'));
        add_action('wp_ajax_aura_finance_budget_execute_import', array(__CLASS__, 'ajax_budget_execute_import'));
        // CRUD Cambio de Divisas (Compra y Venta)
        add_action('wp_ajax_aura_finance_exchange_currency', array(__CLASS__, 'ajax_exchange_currency'));
        add_action('wp_ajax_aura_finance_exchange_history', array(__CLASS__, 'ajax_get_exchange_history'));
        add_action('wp_ajax_aura_finance_exchange_get', array(__CLASS__, 'ajax_get_exchange_detail'));
        add_action('wp_ajax_aura_finance_exchange_update', array(__CLASS__, 'ajax_update_exchange'));
        add_action('wp_ajax_aura_finance_exchange_revert', array(__CLASS__, 'ajax_revert_exchange'));
        add_action('wp_ajax_aura_finance_exchange_delete', array(__CLASS__, 'ajax_delete_exchange'));
    }

    public static function maybe_install() {
        if (get_option(self::DB_VERSION_OPTION) !== self::DB_VERSION) {
            self::create_tables();
            self::migrate_transactions_columns();
            self::migrate_settlements_phase3_columns();
            self::migrate_reimbursements_counterparty_model();
            self::migrate_third_parties_phase2_columns();
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
        }

        self::migrate_settlements_phase3_columns();
        self::migrate_currency_exchanges_table();
        self::migrate_usd_ledger_to_accounts();
        self::migrate_petty_cash_counterparty_model();

        self::ensure_overdue_cron_scheduled();
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $accounts = $wpdb->prefix . 'aura_finance_accounts';
        $movements = $wpdb->prefix . 'aura_finance_account_movements';
        $settlements = $wpdb->prefix . 'aura_finance_petty_cash_settlements';
        $reimbursements = $wpdb->prefix . 'aura_finance_reimbursements';
        $third_parties = $wpdb->prefix . 'aura_finance_third_parties';
        $budget_envelopes = $wpdb->prefix . 'aura_finance_budget_envelopes';
        $budget_monthly = $wpdb->prefix . 'aura_finance_budget_monthly';
        $petty_cash_expenses = $wpdb->prefix . 'aura_finance_petty_cash_expenses';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql_accounts = "CREATE TABLE {$accounts} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            account_type VARCHAR(32) NOT NULL DEFAULT 'bank_account',
            currency VARCHAR(10) NOT NULL DEFAULT 'COP',
            owner_user_id BIGINT UNSIGNED NULL,
            institution VARCHAR(191) NULL,
            account_number_masked VARCHAR(50) NULL,
            initial_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            current_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            meta_json LONGTEXT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_type (account_type),
            KEY idx_currency (currency),
            KEY idx_owner (owner_user_id),
            KEY idx_active (is_active)
        ) {$charset_collate};";

        $sql_movements = "CREATE TABLE {$movements} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED NULL,
            movement_type ENUM('opening','credit','debit','transfer_in','transfer_out','adjustment') NOT NULL DEFAULT 'adjustment',
            amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'COP',
            exchange_rate DECIMAL(12,4) NULL,
            reference_type ENUM('transaction','petty_cash_settlement','reimbursement','manual') NOT NULL DEFAULT 'manual',
            reference_id BIGINT UNSIGNED NULL,
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_account (account_id),
            KEY idx_transaction (transaction_id),
            KEY idx_reference (reference_type, reference_id),
            KEY idx_created (created_at)
        ) {$charset_collate};";

        $sql_settlements = "CREATE TABLE {$settlements} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            petty_cash_account_id BIGINT UNSIGNED NOT NULL,
            responsible_user_id BIGINT UNSIGNED NULL,
            counterparty_id BIGINT UNSIGNED NULL,
            delivery_type VARCHAR(50) NOT NULL DEFAULT 'purchase_errand',
            category_id BIGINT UNSIGNED NULL,
            delivered_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            spent_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            returned_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            status ENUM('open','submitted','approved','closed','rejected') NOT NULL DEFAULT 'open',
            due_date DATETIME NULL,
            last_overdue_alert_at DATETIME NULL,
            evidence_json LONGTEXT NULL,
            notes TEXT NULL,
            approved_by BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_account (petty_cash_account_id),
            KEY idx_responsible (responsible_user_id),
            KEY idx_counterparty (counterparty_id),
            KEY idx_delivery_type (delivery_type),
            KEY idx_status (status),
            KEY idx_due_date (due_date)
        ) {$charset_collate};";

        $sql_reimbursements = "CREATE TABLE {$reimbursements} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            person_user_id BIGINT UNSIGNED NULL,
            counterparty_id BIGINT UNSIGNED NULL,
            origin_transaction_id BIGINT UNSIGNED NULL,
            owed_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            paid_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            status ENUM('pending','partial','paid','cancelled') NOT NULL DEFAULT 'pending',
            paying_account_id BIGINT UNSIGNED NULL,
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_person (person_user_id),
            KEY idx_counterparty (counterparty_id),
            KEY idx_status (status),
            KEY idx_origin_tx (origin_transaction_id)
        ) {$charset_collate};";

        $sql_third_parties = "CREATE TABLE {$third_parties} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(191) NOT NULL,
            party_type VARCHAR(50) NOT NULL DEFAULT 'company',
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
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_name (full_name),
            KEY idx_party_type (party_type),
            KEY idx_active (is_active),
            KEY idx_wp_user (wp_user_id),
            KEY idx_logo (logo_id)
        ) {$charset_collate};";

        $sql_budget_envelopes = "CREATE TABLE {$budget_envelopes} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            fiscal_year SMALLINT UNSIGNED NOT NULL,
            scope_type ENUM('global','fund','account','category') NOT NULL DEFAULT 'global',
            scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            annual_limit DECIMAL(18,2) NOT NULL DEFAULT 0,
            annual_spent DECIMAL(18,2) NOT NULL DEFAULT 0,
            exceed_policy ENUM('warn','block') NOT NULL DEFAULT 'warn',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_year_scope (fiscal_year, scope_type, scope_id),
            KEY idx_year (fiscal_year),
            KEY idx_active (is_active)
        ) {$charset_collate};";

        $sql_budget_monthly = "CREATE TABLE {$budget_monthly} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            envelope_id BIGINT UNSIGNED NOT NULL,
            month_num TINYINT UNSIGNED NOT NULL,
            monthly_limit DECIMAL(18,2) NOT NULL DEFAULT 0,
            monthly_spent DECIMAL(18,2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_envelope_month (envelope_id, month_num),
            KEY idx_month (month_num)
        ) {$charset_collate};";

        $sql_petty_cash_expenses = "CREATE TABLE {$petty_cash_expenses} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            settlement_id BIGINT UNSIGNED NOT NULL,
            category_id BIGINT UNSIGNED NULL,
            amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            receipt_url VARCHAR(1024) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_settlement (settlement_id),
            KEY idx_category (category_id)
        ) {$charset_collate};";

        $currency_exchanges = $wpdb->prefix . 'aura_finance_currency_exchanges';
        $sql_currency_exchanges = "CREATE TABLE {$currency_exchanges} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_account_id BIGINT UNSIGNED NOT NULL,
            target_account_id BIGINT UNSIGNED NOT NULL,
            direction ENUM('local_to_usd', 'usd_to_local') NOT NULL DEFAULT 'local_to_usd',
            amount_usd DECIMAL(18,2) NOT NULL DEFAULT 0,
            amount_local DECIMAL(18,2) NOT NULL DEFAULT 0,
            exchange_rate DECIMAL(12,4) NOT NULL DEFAULT 1,
            source_currency VARCHAR(10) NOT NULL DEFAULT 'COP',
            target_currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            source_old_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            source_new_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            target_old_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            target_new_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            source_movement_id BIGINT UNSIGNED NULL,
            target_movement_id BIGINT UNSIGNED NULL,
            status ENUM('completed', 'reverted') NOT NULL DEFAULT 'completed',
            notes TEXT NULL,
            revert_reason TEXT NULL,
            reverted_by BIGINT UNSIGNED NULL,
            reverted_at DATETIME NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_source (source_account_id),
            KEY idx_target (target_account_id),
            KEY idx_direction (direction),
            KEY idx_status (status),
            KEY idx_created_by (created_by),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql_accounts);
        dbDelta($sql_movements);
        dbDelta($sql_settlements);
        dbDelta($sql_reimbursements);
        dbDelta($sql_third_parties);
        dbDelta($sql_budget_envelopes);
        dbDelta($sql_budget_monthly);
        dbDelta($sql_petty_cash_expenses);
        dbDelta($sql_currency_exchanges);
    }

    private static function migrate_reimbursements_counterparty_model() {
        global $wpdb;

        $reimbursements = $wpdb->prefix . 'aura_finance_reimbursements';
        $third_parties = $wpdb->prefix . 'aura_finance_third_parties';

        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$reimbursements}", 0);
        if (!is_array($columns)) {
            return;
        }

        if (!in_array('counterparty_id', $columns, true)) {
            $wpdb->query("ALTER TABLE {$reimbursements} ADD COLUMN counterparty_id BIGINT UNSIGNED NULL AFTER person_user_id");
            $wpdb->query("ALTER TABLE {$reimbursements} ADD KEY idx_counterparty (counterparty_id)");
        }

        $person_user_col = $wpdb->get_row("SHOW COLUMNS FROM {$reimbursements} LIKE 'person_user_id'", ARRAY_A);
        if (is_array($person_user_col) && isset($person_user_col['Null']) && strtoupper((string) $person_user_col['Null']) === 'NO') {
            $wpdb->query("ALTER TABLE {$reimbursements} MODIFY person_user_id BIGINT UNSIGNED NULL");
        }

        // Backfill: convert legacy person_user_id rows into third-party references.
        $legacy_rows = $wpdb->get_results(
            "SELECT r.id, r.person_user_id, u.display_name
             FROM {$reimbursements} r
             LEFT JOIN {$wpdb->users} u ON u.ID = r.person_user_id
             WHERE r.counterparty_id IS NULL
               AND r.person_user_id IS NOT NULL
               AND r.person_user_id > 0
             LIMIT 10000",
            ARRAY_A
        );

        if (empty($legacy_rows)) {
            return;
        }

        foreach ($legacy_rows as $row) {
            $full_name = sanitize_text_field((string) ($row['display_name'] ?? ''));
            if ($full_name === '') {
                continue;
            }

            $counterparty_id = self::ensure_third_party($full_name, array(), get_current_user_id());
            if ($counterparty_id <= 0) {
                continue;
            }

            $wpdb->update(
                $reimbursements,
                array('counterparty_id' => $counterparty_id),
                array('id' => (int) $row['id']),
                array('%d'),
                array('%d')
            );
        }
    }

    private static function migrate_third_parties_phase2_columns() {
        global $wpdb;

        $table = $wpdb->prefix . 'aura_finance_third_parties';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!is_array($columns)) {
            return;
        }

        if (!in_array('wp_user_id', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN wp_user_id BIGINT UNSIGNED NULL AFTER is_active");
            $wpdb->query("ALTER TABLE {$table} ADD KEY idx_wp_user (wp_user_id)");
        }

        if (!in_array('party_type', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN party_type VARCHAR(50) NOT NULL DEFAULT 'company' AFTER full_name");
            $wpdb->query("ALTER TABLE {$table} ADD KEY idx_party_type (party_type)");
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
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN logo_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER notes");
            $wpdb->query("ALTER TABLE {$table} ADD KEY idx_logo (logo_id)");
        }
    }

    private static function migrate_transactions_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!is_array($columns)) {
            return;
        }

        if (!in_array('source_account_id', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN source_account_id BIGINT UNSIGNED NULL AFTER payment_method");
        }

        if (!in_array('destination_account_id', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN destination_account_id BIGINT UNSIGNED NULL AFTER source_account_id");
        }

        if (!in_array('counterparty_type', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN counterparty_type VARCHAR(30) NULL AFTER destination_account_id");
        }

        if (!in_array('counterparty_user_id', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN counterparty_user_id BIGINT UNSIGNED NULL AFTER counterparty_type");
        }

        if (!in_array('third_party_id', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN third_party_id BIGINT UNSIGNED NULL AFTER counterparty_user_id");
            $wpdb->query("ALTER TABLE {$table} ADD KEY idx_third_party (third_party_id)");
        }
    }

    public static function migrate_settlements_phase3_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_petty_cash_settlements';

        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!is_array($columns) || empty($columns)) {
            return;
        }

        if (!in_array('due_date', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN due_date DATETIME NULL");
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table}
                 SET due_date = DATE_ADD(created_at, INTERVAL %d DAY)
                 WHERE due_date IS NULL",
                self::PETTY_CASH_DEFAULT_DUE_DAYS
            ));
        }

        if (!in_array('last_overdue_alert_at', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN last_overdue_alert_at DATETIME NULL");
        }

        if (!in_array('delivery_type', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN delivery_type VARCHAR(50) NOT NULL DEFAULT 'purchase_errand'");
            $existing_indices = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'idx_delivery_type'", ARRAY_A);
            if (empty($existing_indices)) {
                $wpdb->query("ALTER TABLE {$table} ADD KEY idx_delivery_type (delivery_type)");
            }

            // Retrocompatibilidad: marcar registros existentes de programas como 'program_budget' y ampliar vigencia a 6 meses
            $wpdb->query("UPDATE {$table} 
                          SET delivery_type = 'program_budget',
                              due_date = DATE_ADD(created_at, INTERVAL 180 DAY)
                          WHERE LOWER(notes) LIKE '%programa%'");
        }
    }

    private static function ensure_overdue_cron_scheduled() {
        if (!wp_next_scheduled(self::PETTY_CASH_OVERDUE_CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::PETTY_CASH_OVERDUE_CRON_HOOK);
        }
    }

    public static function add_menu() {
        if (!(current_user_can('aura_finance_view_all') || current_user_can('aura_finance_accounts_manage') || current_user_can('manage_options'))) {
            return;
        }

        add_submenu_page(
            'aura-financial-dashboard',
            __('Bancos y Cuentas', 'aura-suite'),
            __('Bancos', 'aura-suite'),
            'read',
            'aura-financial-accounts',
            array(__CLASS__, 'render_page')
        );
    }

    public static function enqueue_assets($hook) {
        if (strpos($hook, 'aura-financial-accounts') === false) {
            return;
        }

        // Estilos específicos del módulo Bancos y Cuentas (tarjetas KPI, tablas, presupuesto, etc.)
        wp_enqueue_style(
            'aura-financial-accounts-css',
            AURA_PLUGIN_URL . 'assets/css/financial-accounts.css',
            array('aura-design-system'),
            AURA_VERSION . '.' . (file_exists(AURA_PLUGIN_DIR . 'assets/css/financial-accounts.css') ? filemtime(AURA_PLUGIN_DIR . 'assets/css/financial-accounts.css') : time())
        );

        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            array(),
            '4.1.0'
        );

        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            array('jquery'),
            '4.1.0',
            true
        );

        // Autocomplete y selector reutilizable de Terceros / Entidades
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css');
        $css_tp_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/css/third-parties-directory.css');
        wp_enqueue_style('aura-third-parties-directory-css', AURA_PLUGIN_URL . 'assets/css/third-parties-directory.css', array(), $css_tp_ver);

        $js_tp_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/js/third-party-selector.js');
        wp_enqueue_script('aura-third-party-selector', AURA_PLUGIN_URL . 'assets/js/third-party-selector.js', array('jquery', 'jquery-ui-autocomplete'), $js_tp_ver, true);
        wp_localize_script('aura-third-party-selector', 'auraCounterpartiesData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('aura_search_counterparties_nonce'),
            'roles'   => class_exists('Aura_Third_Parties') ? Aura_Third_Parties::get_accounting_roles() : array(),
        ));

        wp_enqueue_script(
            'aura-financial-accounts',
            AURA_PLUGIN_URL . 'assets/js/financial-accounts.js',
            array('jquery', 'datatables-js', 'datatables-responsive-js', 'aura-ui-core', 'aura-third-party-selector'),
            AURA_VERSION . '.' . time(),
            true
        );

        wp_enqueue_media();

        wp_localize_script('aura-financial-accounts', 'auraFinancialAccounts', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aura_financial_accounts_nonce'),
            'canManage' => (current_user_can('aura_finance_view_all') || current_user_can('manage_options')),
            'canExchangeCreate' => (current_user_can('aura_finance_exchange_create') || current_user_can('manage_options') || current_user_can('aura_finance_accounts_manage')),
            'canExchangeEdit'   => (current_user_can('aura_finance_exchange_edit') || current_user_can('manage_options') || current_user_can('aura_finance_accounts_manage')),
            'canExchangeDelete' => (current_user_can('aura_finance_exchange_delete') || current_user_can('manage_options')),
            'canExchangeView'   => (current_user_can('aura_finance_exchange_view') || current_user_can('manage_options') || current_user_can('aura_finance_view_all')),
            'defaultDueDays' => self::PETTY_CASH_DEFAULT_DUE_DAYS,
            'appUsers' => self::get_app_users_for_reimbursements(),
            'initialFilters' => array(
                'type' => sanitize_key(wp_unslash($_GET['account_type'] ?? '')),
                'currency' => strtoupper(sanitize_text_field(wp_unslash($_GET['currency'] ?? ''))),
            ),
            'i18n' => array(
                'saving' => __('Guardando...', 'aura-suite'),
                'saved' => __('Cuenta guardada correctamente.', 'aura-suite'),
                'deleted' => __('Cuenta eliminada correctamente.', 'aura-suite'),
                'deleteConfirm' => __('¿Eliminar esta cuenta? Esta acción no elimina transacciones históricas.', 'aura-suite'),
                'exchangeRevertConfirm' => __('¿Estás seguro de anular y revertir esta operación de cambio de divisa? Se restituirán los saldos en ambas cuentas.', 'aura-suite'),
                'exchangeDeleteConfirm' => __('¿Eliminar permanentemente este registro del historial de auditoría?', 'aura-suite'),
                'error' => __('Error al procesar la solicitud.', 'aura-suite'),
                'uploadNoPermission' => __('No tienes permisos para subir archivos de evidencia.', 'aura-suite'),
                'importing' => __('Importando...', 'aura-suite'),
                'importDone' => __('Importación completada.', 'aura-suite'),
                'reimbursementPayConfirm' => __('¿Registrar este pago de reembolso?', 'aura-suite'),
            ),
        ));
    }

    public static function render_page() {
        if (!(current_user_can('aura_finance_view_all') || current_user_can('manage_options'))) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'aura-suite'));
        }

        include AURA_PLUGIN_DIR . 'templates/financial/accounts-page.php';
    }

    public static function ajax_list_accounts() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_accounts';

        $rows = $wpdb->get_results(
            "SELECT id, name, account_type, currency, institution, account_number_masked, initial_balance, current_balance, is_active, owner_user_id
             FROM {$table}
             WHERE deleted_at IS NULL
             ORDER BY is_active DESC, id DESC",
            ARRAY_A
        );

        wp_send_json_success(array('accounts' => $rows));
    }

    public static function ajax_save_account() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_accounts';

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $account_type = sanitize_key(wp_unslash($_POST['account_type'] ?? 'bank_account'));
        $currency = strtoupper(sanitize_text_field(wp_unslash($_POST['currency'] ?? 'COP')));
        $owner_user_id = !empty($_POST['owner_user_id']) ? absint($_POST['owner_user_id']) : null;
        $institution = sanitize_text_field(wp_unslash($_POST['institution'] ?? ''));
        $account_number_masked = sanitize_text_field(wp_unslash($_POST['account_number_masked'] ?? ''));
        $initial_balance = isset($_POST['initial_balance']) ? floatval($_POST['initial_balance']) : 0;
        $current_balance = isset($_POST['current_balance']) ? floatval($_POST['current_balance']) : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) {
            wp_send_json_error(array('message' => __('El nombre de la cuenta es obligatorio.', 'aura-suite')));
        }

        $valid_types = array('bank_account', 'petty_cash', 'contributions_fund', 'usd_cash', 'eur_cash', 'cad_cash', 'foreign_cash', 'custom');
        if (!in_array($account_type, $valid_types, true)) {
            $account_type = 'custom';
        }

        if ($id > 0) {
            error_log("[AURA] UPDATE Account $id: " . json_encode(array(
                'name' => $name,
                'current_balance' => $current_balance,
            )));

            $update_result = $wpdb->update(
                $table,
                array(
                    'name' => $name,
                    'account_type' => $account_type,
                    'currency' => $currency,
                    'owner_user_id' => $owner_user_id,
                    'institution' => $institution,
                    'account_number_masked' => $account_number_masked,
                    'initial_balance' => $initial_balance,
                    'current_balance' => $current_balance,
                    'is_active' => $is_active,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $id),
                array('%s', '%s', '%s', '%d', '%s', '%s', '%f', '%f', '%d', '%s'),
                array('%d')
            );

            error_log("[AURA] UPDATE Result: $update_result, Error: " . $wpdb->last_error);

            if ($update_result === false) {
                wp_send_json_error(array('message' => __('Error al actualizar la cuenta: ' . $wpdb->last_error, 'aura-suite')));
            }

            wp_send_json_success(array('id' => $id, 'message' => __('Cuenta actualizada correctamente.', 'aura-suite')));
        }

        $insert_result = $wpdb->insert(
            $table,
            array(
                'name' => $name,
                'account_type' => $account_type,
                'currency' => $currency,
                'owner_user_id' => $owner_user_id,
                'institution' => $institution,
                'account_number_masked' => $account_number_masked,
                'initial_balance' => $initial_balance,
                'current_balance' => $current_balance,
                'is_active' => $is_active,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%f', '%f', '%d', '%d', '%s')
        );

        if (!$insert_result) {
            wp_send_json_error(array('message' => __('No se pudo crear la cuenta.', 'aura-suite')));
        }

        wp_send_json_success(array('id' => (int) $wpdb->insert_id));
    }

    public static function ajax_delete_account() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_accounts';

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id <= 0) {
            wp_send_json_error(array('message' => __('ID inválido.', 'aura-suite')));
        }

        $ok = $wpdb->update(
            $table,
            array('deleted_at' => current_time('mysql'), 'is_active' => 0),
            array('id' => $id),
            array('%s', '%d'),
            array('%d')
        );

        if ($ok === false) {
            wp_send_json_error(array('message' => __('No se pudo eliminar la cuenta.', 'aura-suite')));
        }

        wp_send_json_success();
    }

    public static function migrate_currency_exchanges_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_currency_exchanges';
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_account_id BIGINT UNSIGNED NOT NULL,
            target_account_id BIGINT UNSIGNED NOT NULL,
            direction VARCHAR(32) NOT NULL DEFAULT 'local_to_usd',
            amount_usd DECIMAL(18,2) NOT NULL DEFAULT 0,
            amount_local DECIMAL(18,2) NOT NULL DEFAULT 0,
            exchange_rate DECIMAL(12,4) NOT NULL DEFAULT 1,
            source_currency VARCHAR(10) NOT NULL DEFAULT 'COP',
            target_currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            source_old_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            source_new_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            target_old_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            target_new_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            source_movement_id BIGINT UNSIGNED NULL,
            target_movement_id BIGINT UNSIGNED NULL,
            status ENUM('completed', 'reverted') NOT NULL DEFAULT 'completed',
            notes TEXT NULL,
            revert_reason TEXT NULL,
            reverted_by BIGINT UNSIGNED NULL,
            reverted_at DATETIME NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_source (source_account_id),
            KEY idx_target (target_account_id),
            KEY idx_direction (direction),
            KEY idx_status (status),
            KEY idx_created_by (created_by),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);

        // Migración silenciosa de columnas si vienen de versiones anteriores con ENUM estricto
        $accounts_table = $wpdb->prefix . 'aura_finance_accounts';
        @$wpdb->query("ALTER TABLE {$accounts_table} MODIFY COLUMN account_type VARCHAR(32) NOT NULL DEFAULT 'bank_account'");
        @$wpdb->query("ALTER TABLE {$table} MODIFY COLUMN direction VARCHAR(32) NOT NULL DEFAULT 'local_to_usd'");

        // Migración de retrocompatibilidad desde aura_finance_audit_log
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count === 0) {
            $audit_table = $wpdb->prefix . 'aura_finance_audit_log';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$audit_table}'") === $audit_table) {
                $logs = $wpdb->get_results("SELECT * FROM {$audit_table} WHERE action = 'currency_exchanged' ORDER BY id ASC");
                if (!empty($logs)) {
                    foreach ($logs as $l) {
                        $val = json_decode($l->new_value, true);
                        if (!is_array($val)) continue;

                        $wpdb->insert($table, array(
                            'source_account_id'  => (int) ($val['source_id'] ?? 0),
                            'target_account_id'  => (int) ($val['target_id'] ?? 0),
                            'direction'          => $val['direction'] ?? 'local_to_usd',
                            'amount_usd'         => floatval($val['amount_usd'] ?? 0),
                            'amount_local'       => floatval($val['amount_local'] ?? 0),
                            'exchange_rate'      => floatval($val['exchange_rate'] ?? 1),
                            'source_currency'    => $val['source_currency'] ?? 'COP',
                            'target_currency'    => $val['target_currency'] ?? 'USD',
                            'source_old_balance' => floatval($val['source_old_balance'] ?? 0),
                            'source_new_balance' => floatval($val['source_new_balance'] ?? 0),
                            'target_old_balance' => floatval($val['target_old_balance'] ?? 0),
                            'target_new_balance' => floatval($val['target_new_balance'] ?? 0),
                            'source_movement_id' => !empty($val['out_movement_id']) ? (int) $val['out_movement_id'] : null,
                            'target_movement_id' => !empty($val['in_movement_id']) ? (int) $val['in_movement_id'] : null,
                            'status'             => 'completed',
                            'notes'              => $val['notes'] ?? '',
                            'created_by'         => (int) ($l->user_id ?? 1),
                            'created_at'         => $l->created_at,
                        ));
                    }
                }
            }
        }
    }

    public static function ajax_exchange_currency() {
        self::check_ajax_permissions('aura_finance_exchange_create');

        global $wpdb;
        $accounts_table   = $wpdb->prefix . 'aura_finance_accounts';
        $exchanges_table  = $wpdb->prefix . 'aura_finance_currency_exchanges';
        $movements_table  = $wpdb->prefix . 'aura_finance_account_movements';

        self::migrate_currency_exchanges_table();

        $source_id     = isset($_POST['source_account_id']) ? absint($_POST['source_account_id']) : 0;
        $target_id     = isset($_POST['target_account_id']) ? absint($_POST['target_account_id']) : 0;
        $amount_usd    = isset($_POST['amount_usd']) ? floatval($_POST['amount_usd']) : 0;
        $exchange_rate = isset($_POST['exchange_rate']) ? floatval($_POST['exchange_rate']) : 0;
        $direction     = isset($_POST['direction']) ? sanitize_key($_POST['direction']) : 'auto';
        $notes         = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

        if ($source_id <= 0 || $target_id <= 0 || $amount_usd <= 0 || $exchange_rate <= 0) {
            wp_send_json_error(array('message' => __('Datos incompletos para el cambio de divisa.', 'aura-suite')));
        }
        
        if ($source_id === $target_id) {
            wp_send_json_error(array('message' => __('La cuenta origen y destino no pueden ser la misma.', 'aura-suite')));
        }

        $source_acc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$accounts_table} WHERE id = %d AND is_active = 1", $source_id));
        $target_acc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$accounts_table} WHERE id = %d AND is_active = 1", $target_id));

        if (!$source_acc || !$target_acc) {
            wp_send_json_error(array('message' => __('Una de las cuentas no existe o está inactiva.', 'aura-suite')));
        }

        $source_curr = strtoupper((string) $source_acc->currency);
        $target_curr = strtoupper((string) $target_acc->currency);

        if ($source_curr === $target_curr) {
            wp_send_json_error(array('message' => __('Las cuentas deben tener monedas diferentes para un cambio de divisa.', 'aura-suite')));
        }

        $amount_local = round($amount_usd * $exchange_rate, 2);

        // Determinar dirección del cambio de forma multidivisa
        // Si el cliente envía 'usd_to_local' / 'foreign_to_local', o si la cuenta origen es la extranjera
        $is_local_to_foreign = true;
        if ($direction === 'usd_to_local' || $direction === 'foreign_to_local') {
            $is_local_to_foreign = false;
        } elseif ($direction === 'local_to_usd' || $direction === 'local_to_foreign') {
            $is_local_to_foreign = true;
        } else {
            // Detección automática por divisa
            $known_foreign = array('USD', 'EUR', 'CAD', 'GBP', 'CHF');
            if (in_array($source_curr, $known_foreign, true) && !in_array($target_curr, $known_foreign, true)) {
                $is_local_to_foreign = false;
            } else {
                $is_local_to_foreign = true;
            }
        }

        $foreign_curr = $is_local_to_foreign ? $target_curr : $source_curr;
        $local_curr   = $is_local_to_foreign ? $source_curr : $target_curr;

        $debit_amount  = $is_local_to_foreign ? $amount_local : $amount_usd;
        $credit_amount = $is_local_to_foreign ? $amount_usd : $amount_local;

        // Validar saldo suficiente en la cuenta de origen
        if (floatval($source_acc->current_balance) < $debit_amount) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Saldo insuficiente en %s. Requiere %s %s pero solo dispone de %s %s.', 'aura-suite'),
                    esc_html($source_acc->name),
                    number_format($debit_amount, 2),
                    $source_curr,
                    number_format(floatval($source_acc->current_balance), 2),
                    $source_curr
                )
            ));
        }

        $wpdb->query('START TRANSACTION');

        try {
            $current_user = wp_get_current_user();
            $user_display_name = $current_user && $current_user->exists() ? $current_user->display_name : 'Sistema';
            $user_id = get_current_user_id();

            $out_movement_id = 0;
            $in_movement_id = 0;

            // Actualizar saldos
            $new_source_balance = floatval($source_acc->current_balance) - $debit_amount;
            $new_target_balance = floatval($target_acc->current_balance) + $credit_amount;

            $wpdb->update($accounts_table, array('current_balance' => $new_source_balance, 'updated_at' => current_time('mysql')), array('id' => $source_id));
            $wpdb->update($accounts_table, array('current_balance' => $new_target_balance, 'updated_at' => current_time('mysql')), array('id' => $target_id));

            if ($is_local_to_foreign) {
                // Sale moneda local de source, entra divisa extranjera a target
                $wpdb->insert($movements_table, array(
                    'account_id'    => $source_id,
                    'movement_type' => 'transfer_out',
                    'amount'        => -$debit_amount,
                    'currency'      => $source_curr,
                    'exchange_rate' => $exchange_rate,
                    'reference_type'=> 'manual',
                    'notes'         => sprintf(__('Cambio a %s para %s (%s %s a tasa %s). %s', 'aura-suite'), $foreign_curr, $target_acc->name, number_format($credit_amount, 2), $foreign_curr, $exchange_rate, $notes),
                    'created_by'    => $user_id,
                    'created_at'    => current_time('mysql')
                ));
                $out_movement_id = (int) $wpdb->insert_id;

                $wpdb->insert($movements_table, array(
                    'account_id'    => $target_id,
                    'movement_type' => 'transfer_in',
                    'amount'        => $credit_amount,
                    'currency'      => $target_curr,
                    'exchange_rate' => $exchange_rate,
                    'reference_type'=> 'manual',
                    'reference_id'  => $out_movement_id,
                    'notes'         => sprintf(__('Ingreso %s comprado desde %s (Pagado %s %s a tasa %s). %s', 'aura-suite'), $foreign_curr, $source_acc->name, number_format($debit_amount, 2), $source_curr, $exchange_rate, $notes),
                    'created_by'    => $user_id,
                    'created_at'    => current_time('mysql')
                ));
                $in_movement_id = (int) $wpdb->insert_id;
            } else {
                // Sale divisa extranjera de source, entra moneda local a target
                $wpdb->insert($movements_table, array(
                    'account_id'    => $source_id,
                    'movement_type' => 'transfer_out',
                    'amount'        => -$debit_amount,
                    'currency'      => $source_curr,
                    'exchange_rate' => $exchange_rate,
                    'reference_type'=> 'manual',
                    'notes'         => sprintf(__('Venta de %s hacia %s (%s %s a tasa %s). %s', 'aura-suite'), $foreign_curr, $target_acc->name, number_format($credit_amount, 2), $target_curr, $exchange_rate, $notes),
                    'created_by'    => $user_id,
                    'created_at'    => current_time('mysql')
                ));
                $out_movement_id = (int) $wpdb->insert_id;

                $wpdb->insert($movements_table, array(
                    'account_id'    => $target_id,
                    'movement_type' => 'transfer_in',
                    'amount'        => $credit_amount,
                    'currency'      => $target_curr,
                    'exchange_rate' => $exchange_rate,
                    'reference_type'=> 'manual',
                    'reference_id'  => $out_movement_id,
                    'notes'         => sprintf(__('Ingreso por cambio de %s desde %s (%s %s a tasa %s). %s', 'aura-suite'), $foreign_curr, $source_acc->name, number_format($debit_amount, 2), $source_curr, $exchange_rate, $notes),
                    'created_by'    => $user_id,
                    'created_at'    => current_time('mysql')
                ));
                $in_movement_id = (int) $wpdb->insert_id;
            }

            // Enlazar la salida con la entrada
            if ($out_movement_id && $in_movement_id) {
                $wpdb->update($movements_table, array('reference_id' => $in_movement_id), array('id' => $out_movement_id));
            }

            // Guardar en tabla maestra dedicada de cambios de divisas
            $direction_saved = $is_local_to_foreign ? ($foreign_curr === 'USD' ? 'local_to_usd' : 'local_to_foreign') : ($foreign_curr === 'USD' ? 'usd_to_local' : 'foreign_to_local');

            $wpdb->insert($exchanges_table, array(
                'source_account_id'  => $source_id,
                'target_account_id'  => $target_id,
                'direction'          => $direction_saved,
                'amount_usd'         => $amount_usd,
                'amount_local'       => $amount_local,
                'exchange_rate'      => $exchange_rate,
                'source_currency'    => $source_curr,
                'target_currency'    => $target_curr,
                'source_old_balance' => floatval($source_acc->current_balance),
                'source_new_balance' => $new_source_balance,
                'target_old_balance' => floatval($target_acc->current_balance),
                'target_new_balance' => $new_target_balance,
                'source_movement_id' => $out_movement_id,
                'target_movement_id' => $in_movement_id,
                'status'             => 'completed',
                'notes'              => $notes,
                'created_by'         => $user_id,
                'created_at'         => current_time('mysql'),
            ));
            $exchange_id = (int) $wpdb->insert_id;

            // Registrar formalmente en Auditoría (Aura_Financial_Audit)
            if (class_exists('Aura_Financial_Audit')) {
                $op_label = $is_local_to_foreign 
                    ? sprintf(__('Compra de %s (%s ➔ %s)', 'aura-suite'), $foreign_curr, $source_curr, $target_curr)
                    : sprintf(__('Venta de %s (%s ➔ %s)', 'aura-suite'), $foreign_curr, $source_curr, $target_curr);

                Aura_Financial_Audit::log_action(
                    'currency_exchanged',
                    'currency_exchange',
                    $exchange_id,
                    array(
                        'source_id'          => $source_id,
                        'source_name'        => $source_acc->name,
                        'source_balance'     => floatval($source_acc->current_balance),
                        'target_id'          => $target_id,
                        'target_name'        => $target_acc->name,
                        'target_balance'     => floatval($target_acc->current_balance),
                    ),
                    array(
                        'exchange_id'        => $exchange_id,
                        'direction'          => $direction_saved,
                        'operation_label'    => $op_label,
                        'foreign_currency'   => $foreign_curr,
                        'source_id'          => $source_id,
                        'source_name'        => $source_acc->name,
                        'source_currency'    => $source_curr,
                        'source_old_balance' => floatval($source_acc->current_balance),
                        'source_new_balance' => $new_source_balance,
                        'target_id'          => $target_id,
                        'target_name'        => $target_acc->name,
                        'target_currency'    => $target_curr,
                        'target_old_balance' => floatval($target_acc->current_balance),
                        'target_new_balance' => $new_target_balance,
                        'amount_usd'         => $amount_usd,
                        'amount_local'       => $amount_local,
                        'exchange_rate'      => $exchange_rate,
                        'notes'              => $notes,
                        'out_movement_id'    => $out_movement_id,
                        'in_movement_id'     => $in_movement_id,
                        'user_id'            => $user_id,
                        'user_name'          => $user_display_name,
                        'created_at'         => current_time('mysql'),
                    )
                );
            }

            $wpdb->query('COMMIT');

            wp_send_json_success(array(
                'message'            => __('Cambio de divisa registrado y auditado exitosamente.', 'aura-suite'),
                'exchange_id'        => $exchange_id,
                'source_id'          => $source_id,
                'source_new_balance' => $new_source_balance,
                'target_id'          => $target_id,
                'target_new_balance' => $new_target_balance,
                'amount_usd'         => $amount_usd,
                'amount_local'       => $amount_local,
                'exchange_rate'      => $exchange_rate,
                'direction'          => $direction_saved,
                'foreign_currency'   => $foreign_curr
            ));
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => __('Error al procesar la base de datos: ', 'aura-suite') . $e->getMessage()));
        }
    }

    public static function ajax_get_exchange_history() {
        self::check_ajax_permissions('aura_finance_exchange_view');

        global $wpdb;
        $exchanges_table = $wpdb->prefix . 'aura_finance_currency_exchanges';
        $accounts_table  = $wpdb->prefix . 'aura_finance_accounts';
        $users_table     = $wpdb->users;

        self::migrate_currency_exchanges_table();

        $rows = $wpdb->get_results(
            "SELECT e.*, 
                    sa.name AS source_name, 
                    ta.name AS target_name, 
                    u.display_name AS user_name,
                    ru.display_name AS reverted_by_name
             FROM {$exchanges_table} e
             LEFT JOIN {$accounts_table} sa ON sa.id = e.source_account_id
             LEFT JOIN {$accounts_table} ta ON ta.id = e.target_account_id
             LEFT JOIN {$users_table} u ON u.ID = e.created_by
             LEFT JOIN {$users_table} ru ON ru.ID = e.reverted_by
             ORDER BY e.created_at DESC 
             LIMIT 500",
            ARRAY_A
        );

        $records = array();
        $total_usd_bought = 0.0;
        $total_usd_sold   = 0.0;
        $rate_sum         = 0.0;
        $total_ops        = 0;
        $currency_stats   = array();

        if (!empty($rows)) {
            foreach ($rows as $r) {
                $status = $r['status'] ?? 'completed';
                $usd = floatval($r['amount_usd']);
                $local = floatval($r['amount_local']);
                $rate = floatval($r['exchange_rate']);
                $dir = $r['direction'];

                $is_buy = ($dir === 'local_to_usd' || $dir === 'local_to_foreign');
                $foreign_curr = $is_buy ? strtoupper($r['target_currency']) : strtoupper($r['source_currency']);

                if (!isset($currency_stats[$foreign_curr])) {
                    $currency_stats[$foreign_curr] = array('bought' => 0.0, 'sold' => 0.0, 'ops' => 0);
                }

                if ($status === 'completed') {
                    if ($is_buy) {
                        $total_usd_bought += $usd;
                        $currency_stats[$foreign_curr]['bought'] += $usd;
                    } else {
                        $total_usd_sold += $usd;
                        $currency_stats[$foreign_curr]['sold'] += $usd;
                    }
                    $currency_stats[$foreign_curr]['ops']++;
                    $rate_sum += $rate;
                    $total_ops++;
                }

                $dir_label = $is_buy 
                    ? sprintf(__('Compra %s (%s ➔ %s)', 'aura-suite'), $foreign_curr, $r['source_currency'], $r['target_currency'])
                    : sprintf(__('Venta %s (%s ➔ %s)', 'aura-suite'), $foreign_curr, $r['source_currency'], $r['target_currency']);

                $records[] = array(
                    'id'                  => (int) $r['id'],
                    'created_at'          => $r['created_at'],
                    'updated_at'          => $r['updated_at'],
                    'direction'           => $dir,
                    'is_buy'              => $is_buy,
                    'foreign_currency'    => $foreign_curr,
                    'direction_label'     => $dir_label,
                    'status'              => $status,
                    'status_label'        => $status === 'completed' ? __('Completada', 'aura-suite') : __('Revertida', 'aura-suite'),
                    'source_id'           => (int) $r['source_account_id'],
                    'source_name'         => $r['source_name'] ?: sprintf(__('Cuenta #%d', 'aura-suite'), $r['source_account_id']),
                    'source_currency'     => $r['source_currency'],
                    'source_old_balance'  => floatval($r['source_old_balance']),
                    'source_new_balance'  => floatval($r['source_new_balance']),
                    'target_id'           => (int) $r['target_account_id'],
                    'target_name'         => $r['target_name'] ?: sprintf(__('Cuenta #%d', 'aura-suite'), $r['target_account_id']),
                    'target_currency'     => $r['target_currency'],
                    'target_old_balance'  => floatval($r['target_old_balance']),
                    'target_new_balance'  => floatval($r['target_new_balance']),
                    'amount_usd'          => $usd,
                    'amount_foreign'      => $usd,
                    'amount_local'        => $local,
                    'exchange_rate'       => $rate,
                    'notes'               => $r['notes'] ?: '',
                    'revert_reason'       => $r['revert_reason'] ?: '',
                    'reverted_at'         => $r['reverted_at'] ?: '',
                    'reverted_by_name'    => $r['reverted_by_name'] ?: '',
                    'user_name'           => $r['user_name'] ?: 'Sistema',
                    'source_movement_id'  => (int) ($r['source_movement_id'] ?? 0),
                    'target_movement_id'  => (int) ($r['target_movement_id'] ?? 0),
                );
            }
        }

        $avg_rate = $total_ops > 0 ? round($rate_sum / $total_ops, 4) : 0.0;

        wp_send_json_success(array(
            'kpis' => array(
                'total_operations' => $total_ops,
                'total_usd_bought' => $total_usd_bought,
                'total_usd_sold'   => $total_usd_sold,
                'avg_exchange_rate'=> $avg_rate,
                'currency_stats'   => $currency_stats
            ),
            'records' => $records
        ));
    }

    public static function ajax_get_exchange_detail() {
        self::check_ajax_permissions('aura_finance_exchange_view');

        global $wpdb;
        $exchanges_table = $wpdb->prefix . 'aura_finance_currency_exchanges';
        $accounts_table  = $wpdb->prefix . 'aura_finance_accounts';
        $users_table     = $wpdb->users;

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id <= 0) {
            wp_send_json_error(array('message' => __('ID de operación inválido.', 'aura-suite')));
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT e.*, 
                    sa.name AS source_name, 
                    ta.name AS target_name, 
                    u.display_name AS user_name,
                    ru.display_name AS reverted_by_name
             FROM {$exchanges_table} e
             LEFT JOIN {$accounts_table} sa ON sa.id = e.source_account_id
             LEFT JOIN {$accounts_table} ta ON ta.id = e.target_account_id
             LEFT JOIN {$users_table} u ON u.ID = e.created_by
             LEFT JOIN {$users_table} ru ON ru.ID = e.reverted_by
             WHERE e.id = %d",
            $id
        ), ARRAY_A);

        if (!$row) {
            wp_send_json_error(array('message' => __('Operación no encontrada.', 'aura-suite')));
        }

        wp_send_json_success(array('exchange' => $row));
    }

    public static function ajax_update_exchange() {
        self::check_ajax_permissions('aura_finance_exchange_edit');

        global $wpdb;
        $exchanges_table = $wpdb->prefix . 'aura_finance_currency_exchanges';
        $accounts_table  = $wpdb->prefix . 'aura_finance_accounts';
        $movements_table = $wpdb->prefix . 'aura_finance_account_movements';

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

        if ($id <= 0) {
            wp_send_json_error(array('message' => __('ID de operación inválido.', 'aura-suite')));
        }

        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$exchanges_table} WHERE id = %d", $id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('La operación no existe.', 'aura-suite')));
        }

        // Si la operación fue revertida, solo se permiten editar notas/justificación
        $is_full_edit = isset($_POST['amount_usd']) && isset($_POST['exchange_rate']) && isset($_POST['source_account_id']) && isset($_POST['target_account_id']);
        if ($existing->status === 'reverted' && $is_full_edit) {
            // Solo actualizamos notas si ya fue revertida
            $wpdb->update($exchanges_table, array(
                'notes'      => $notes,
                'updated_at' => current_time('mysql'),
            ), array('id' => $id));

            if (class_exists('Aura_Financial_Audit')) {
                Aura_Financial_Audit::log_action(
                    'currency_exchange_updated',
                    'currency_exchange',
                    $id,
                    array('notes' => $existing->notes),
                    array('notes' => $notes, 'updated_by' => get_current_user_id())
                );
            }

            wp_send_json_success(array('message' => __('Notas actualizadas. Los montos de una operación revertida no pueden ser alterados.', 'aura-suite')));
        }

        if (!$is_full_edit) {
            // Solo actualizar notas
            $wpdb->update($exchanges_table, array(
                'notes'      => $notes,
                'updated_at' => current_time('mysql'),
            ), array('id' => $id));

            if (class_exists('Aura_Financial_Audit')) {
                Aura_Financial_Audit::log_action(
                    'currency_exchange_updated',
                    'currency_exchange',
                    $id,
                    array('notes' => $existing->notes),
                    array('notes' => $notes, 'updated_by' => get_current_user_id())
                );
            }

            wp_send_json_success(array('message' => __('Notas de la operación actualizadas correctamente.', 'aura-suite')));
        }

        // Edición completa: cuentas, tasa, montos y notas
        $new_source_id  = absint($_POST['source_account_id']);
        $new_target_id  = absint($_POST['target_account_id']);
        $new_rate       = floatval($_POST['exchange_rate']);
        $new_amount_usd = floatval($_POST['amount_usd']);

        if ($new_source_id <= 0 || $new_target_id <= 0) {
            wp_send_json_error(array('message' => __('Debes seleccionar cuentas de origen y destino válidas.', 'aura-suite')));
        }

        if ($new_source_id === $new_target_id) {
            wp_send_json_error(array('message' => __('La cuenta de origen y destino no pueden ser la misma.', 'aura-suite')));
        }

        if ($new_rate <= 0 || $new_amount_usd <= 0) {
            wp_send_json_error(array('message' => __('La tasa de cambio y el monto en USD deben ser mayores a 0.', 'aura-suite')));
        }

        $new_amount_local = round($new_amount_usd * $new_rate, 2);

        // Obtener cuentas actuales en la base de datos
        $old_source_acc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$accounts_table} WHERE id = %d", $existing->source_account_id));
        $old_target_acc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$accounts_table} WHERE id = %d", $existing->target_account_id));

        $new_source_acc = ($new_source_id === (int) $existing->source_account_id) ? $old_source_acc : $wpdb->get_row($wpdb->prepare("SELECT * FROM {$accounts_table} WHERE id = %d", $new_source_id));
        $new_target_acc = ($new_target_id === (int) $existing->target_account_id) ? $old_target_acc : $wpdb->get_row($wpdb->prepare("SELECT * FROM {$accounts_table} WHERE id = %d", $new_target_id));

        if (!$old_source_acc || !$old_target_acc || !$new_source_acc || !$new_target_acc) {
            wp_send_json_error(array('message' => __('Una de las cuentas involucradas ya no existe en el sistema.', 'aura-suite')));
        }

        $new_source_curr = strtoupper($new_source_acc->currency ?: 'NIO');
        $new_target_curr = strtoupper($new_target_acc->currency ?: 'USD');

        // Determinar dirección de la nueva operación de forma multidivisa
        $known_foreign = array('USD', 'EUR', 'CAD', 'GBP', 'CHF');
        $client_dir = isset($_POST['direction']) ? sanitize_key($_POST['direction']) : '';

        if ($client_dir === 'usd_to_local' || $client_dir === 'foreign_to_local') {
            $new_is_to_foreign = false;
            $new_direction = ($new_source_curr === 'USD') ? 'usd_to_local' : 'foreign_to_local';
        } elseif ($client_dir === 'local_to_usd' || $client_dir === 'local_to_foreign') {
            $new_is_to_foreign = true;
            $new_direction = ($new_target_curr === 'USD') ? 'local_to_usd' : 'local_to_foreign';
        } else {
            if (in_array($new_source_curr, $known_foreign, true) && !in_array($new_target_curr, $known_foreign, true)) {
                $new_is_to_foreign = false;
                $new_direction = ($new_source_curr === 'USD') ? 'usd_to_local' : 'foreign_to_local';
            } else {
                $new_is_to_foreign = true;
                $new_direction = ($new_target_curr === 'USD') ? 'local_to_usd' : 'local_to_foreign';
            }
        }

        $old_is_to_foreign = ($existing->direction === 'local_to_usd' || $existing->direction === 'local_to_foreign');
        $old_debit_from_source = $old_is_to_foreign ? floatval($existing->amount_local) : floatval($existing->amount_usd);
        $old_credit_to_target  = $old_is_to_foreign ? floatval($existing->amount_usd) : floatval($existing->amount_local);

        $new_debit_from_source = $new_is_to_foreign ? $new_amount_local : $new_amount_usd;
        $new_credit_to_target  = $new_is_to_foreign ? $new_amount_usd : $new_amount_local;

        // Calcular saldos simulados para validar suficiencia de fondos
        // 1. Revertir impacto viejo en cuentas viejas
        $simulated_balances = array();
        $simulated_balances[$old_source_acc->id] = floatval($old_source_acc->current_balance) + $old_debit_from_source;
        $simulated_balances[$old_target_acc->id] = (isset($simulated_balances[$old_target_acc->id]) ? $simulated_balances[$old_target_acc->id] : floatval($old_target_acc->current_balance)) - $old_credit_to_target;

        // Validar si al quitar el crédito anterior la cuenta de destino quedaría negativa
        if ($simulated_balances[$old_target_acc->id] < -0.0001) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('No se puede actualizar: la cuenta original de destino "%s" no tiene saldo suficiente para devolver los fondos previos (%s %s). Saldo disponible: %s %s.', 'aura-suite'),
                    esc_html($old_target_acc->name),
                    number_format($old_credit_to_target, 2),
                    esc_html($existing->target_currency),
                    number_format(floatval($old_target_acc->current_balance), 2),
                    esc_html($existing->target_currency)
                )
            ));
        }

        // 2. Aplicar nuevo débito a nueva cuenta de origen
        $sim_new_source = isset($simulated_balances[$new_source_acc->id]) ? $simulated_balances[$new_source_acc->id] : floatval($new_source_acc->current_balance);
        if ($sim_new_source < $new_debit_from_source) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Saldo insuficiente en la cuenta de origen "%s". Requiere %s %s, pero el saldo ajustado disponible es %s %s.', 'aura-suite'),
                    esc_html($new_source_acc->name),
                    number_format($new_debit_from_source, 2),
                    esc_html($new_source_curr),
                    number_format($sim_new_source, 2),
                    esc_html($new_source_curr)
                )
            ));
        }

        // Ejecutar rebalanceo atómico en base de datos
        $wpdb->query('START TRANSACTION');

        try {
            $user_id = get_current_user_id();
            $now = current_time('mysql');

            // 1. Revertir impacto viejo en cuentas viejas
            $wpdb->update($accounts_table, array(
                'current_balance' => floatval($old_source_acc->current_balance) + $old_debit_from_source,
                'updated_at'      => $now
            ), array('id' => $old_source_acc->id));

            // Refrescar saldo si target == source
            $target_bal_now = ($old_target_acc->id === $old_source_acc->id) ? (floatval($old_source_acc->current_balance) + $old_debit_from_source) : floatval($old_target_acc->current_balance);
            $wpdb->update($accounts_table, array(
                'current_balance' => $target_bal_now - $old_credit_to_target,
                'updated_at'      => $now
            ), array('id' => $old_target_acc->id));

            // 2. Aplicar nuevo impacto en nuevas cuentas
            $cur_source_bal = (float) $wpdb->get_var($wpdb->prepare("SELECT current_balance FROM {$accounts_table} WHERE id = %d", $new_source_acc->id));
            $final_source_bal = $cur_source_bal - $new_debit_from_source;
            $wpdb->update($accounts_table, array(
                'current_balance' => $final_source_bal,
                'updated_at'      => $now
            ), array('id' => $new_source_acc->id));

            $cur_target_bal = (float) $wpdb->get_var($wpdb->prepare("SELECT current_balance FROM {$accounts_table} WHERE id = %d", $new_target_acc->id));
            $final_target_bal = $cur_target_bal + $new_credit_to_target;
            $wpdb->update($accounts_table, array(
                'current_balance' => $final_target_bal,
                'updated_at'      => $now
            ), array('id' => $new_target_acc->id));

            // 3. Actualizar o ajustar movimientos contables enlazados
            if ($existing->source_movement_id) {
                $wpdb->update($movements_table, array(
                    'account_id'    => $new_source_acc->id,
                    'amount'        => -$new_debit_from_source,
                    'currency'      => $new_source_curr,
                    'exchange_rate' => $new_rate,
                    'notes'         => sprintf(__('Cambio de Divisas #%d (Modificado): Salida hacia %s (%s). %s', 'aura-suite'), $id, $new_target_acc->name, $notes, $now)
                ), array('id' => $existing->source_movement_id));
            }

            if ($existing->target_movement_id) {
                $wpdb->update($movements_table, array(
                    'account_id'    => $new_target_acc->id,
                    'amount'        => $new_credit_to_target,
                    'currency'      => $new_target_curr,
                    'exchange_rate' => $new_rate,
                    'notes'         => sprintf(__('Cambio de Divisas #%d (Modificado): Entrada desde %s (%s). %s', 'aura-suite'), $id, $new_source_acc->name, $notes, $now)
                ), array('id' => $existing->target_movement_id));
            }

            // 4. Actualizar registro maestro de cambio de divisa
            $wpdb->update($exchanges_table, array(
                'source_account_id'  => $new_source_acc->id,
                'target_account_id'  => $new_target_acc->id,
                'direction'          => $new_direction,
                'amount_usd'         => $new_amount_usd,
                'amount_local'       => $new_amount_local,
                'exchange_rate'      => $new_rate,
                'source_currency'    => $new_source_curr,
                'target_currency'    => $new_target_curr,
                'notes'              => $notes,
                'updated_at'         => $now,
            ), array('id' => $id));

            // 5. Registrar formalmente en auditoría con diff
            if (class_exists('Aura_Financial_Audit')) {
                Aura_Financial_Audit::log_action(
                    'currency_exchange_updated',
                    'currency_exchange',
                    $id,
                    array(
                        'source_id'     => (int) $existing->source_account_id,
                        'target_id'     => (int) $existing->target_account_id,
                        'amount_usd'    => floatval($existing->amount_usd),
                        'amount_local'  => floatval($existing->amount_local),
                        'exchange_rate' => floatval($existing->exchange_rate),
                        'notes'         => $existing->notes
                    ),
                    array(
                        'source_id'          => $new_source_acc->id,
                        'source_name'        => $new_source_acc->name,
                        'target_id'          => $new_target_acc->id,
                        'target_name'        => $new_target_acc->name,
                        'amount_usd'         => $new_amount_usd,
                        'amount_local'       => $new_amount_local,
                        'exchange_rate'      => $new_rate,
                        'notes'              => $notes,
                        'rebalanced_at'      => $now,
                        'updated_by'         => $user_id
                    )
                );
            }

            $wpdb->query('COMMIT');

            wp_send_json_success(array(
                'message'            => __('Operación actualizada y saldos de cuentas rebalanceados correctamente.', 'aura-suite'),
                'id'                 => $id,
                'source_new_balance' => $final_source_bal,
                'target_new_balance' => $final_target_bal
            ));
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => __('Error al rebalancear cuentas: ', 'aura-suite') . $e->getMessage()));
        }
    }

    public static function ajax_revert_exchange() {
        self::check_ajax_permissions('aura_finance_exchange_delete');

        global $wpdb;
        $exchanges_table = $wpdb->prefix . 'aura_finance_currency_exchanges';
        $accounts_table  = $wpdb->prefix . 'aura_finance_accounts';
        $movements_table = $wpdb->prefix . 'aura_finance_account_movements';

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $revert_reason = sanitize_textarea_field(wp_unslash($_POST['revert_reason'] ?? ''));

        if ($id <= 0) {
            wp_send_json_error(array('message' => __('ID de operación inválido.', 'aura-suite')));
        }

        if (empty(trim($revert_reason))) {
            wp_send_json_error(array('message' => __('Debes indicar el motivo de la reversión o anulación.', 'aura-suite')));
        }

        $exchange = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$exchanges_table} WHERE id = %d", $id));
        if (!$exchange) {
            wp_send_json_error(array('message' => __('La operación no existe.', 'aura-suite')));
        }

        if ($exchange->status === 'reverted') {
            wp_send_json_error(array('message' => __('Esta operación ya ha sido revertida previamente.', 'aura-suite')));
        }

        $source_acc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$accounts_table} WHERE id = %d", $exchange->source_account_id));
        $target_acc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$accounts_table} WHERE id = %d", $exchange->target_account_id));

        if (!$source_acc || !$target_acc) {
            wp_send_json_error(array('message' => __('Una de las cuentas involucradas ya no existe.', 'aura-suite')));
        }

        $is_to_foreign = ($exchange->direction === 'local_to_usd' || $exchange->direction === 'local_to_foreign');
        $debit_from_source = $is_to_foreign ? floatval($exchange->amount_local) : floatval($exchange->amount_usd);
        $credit_to_target  = $is_to_foreign ? floatval($exchange->amount_usd) : floatval($exchange->amount_local);

        // Validación de saldo en cuenta de destino
        if (floatval($target_acc->current_balance) < $credit_to_target) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('No se puede revertir: la cuenta destino "%s" no dispone de saldo suficiente (%s %s) para devolver los fondos. Saldo disponible: %s %s.', 'aura-suite'),
                    esc_html($target_acc->name),
                    number_format($credit_to_target, 2),
                    esc_html($exchange->target_currency),
                    number_format(floatval($target_acc->current_balance), 2),
                    esc_html($exchange->target_currency)
                )
            ));
        }

        $wpdb->query('START TRANSACTION');

        try {
            $user_id = get_current_user_id();
            $now = current_time('mysql');

            // 1. Restaurar saldo en origen
            $new_source_balance = floatval($source_acc->current_balance) + $debit_from_source;
            $wpdb->update($accounts_table, array(
                'current_balance' => $new_source_balance,
                'updated_at'      => $now
            ), array('id' => $source_acc->id));

            // 2. Restar saldo en destino
            $new_target_balance = floatval($target_acc->current_balance) - $credit_to_target;
            $wpdb->update($accounts_table, array(
                'current_balance' => $new_target_balance,
                'updated_at'      => $now
            ), array('id' => $target_acc->id));

            // 3. Registrar movimientos contables de reversión
            $wpdb->insert($movements_table, array(
                'account_id'    => $source_acc->id,
                'movement_type' => 'transfer_in',
                'amount'        => $debit_from_source,
                'currency'      => $exchange->source_currency,
                'exchange_rate' => $exchange->exchange_rate,
                'reference_type'=> 'manual',
                'reference_id'  => $id,
                'notes'         => sprintf(__('Reversión Cambio #%d: Restitución de fondos (%s %s). Motivo: %s', 'aura-suite'), $id, number_format($debit_from_source, 2), $exchange->source_currency, $revert_reason),
                'created_by'    => $user_id,
                'created_at'    => $now
            ));

            $wpdb->insert($movements_table, array(
                'account_id'    => $target_acc->id,
                'movement_type' => 'transfer_out',
                'amount'        => -$credit_to_target,
                'currency'      => $exchange->target_currency,
                'exchange_rate' => $exchange->exchange_rate,
                'reference_type'=> 'manual',
                'reference_id'  => $id,
                'notes'         => sprintf(__('Reversión Cambio #%d: Retiro de fondos revertidos (%s %s). Motivo: %s', 'aura-suite'), $id, number_format($credit_to_target, 2), $exchange->target_currency, $revert_reason),
                'created_by'    => $user_id,
                'created_at'    => $now
            ));

            // 4. Actualizar registro a 'reverted'
            $wpdb->update($exchanges_table, array(
                'status'        => 'reverted',
                'revert_reason' => $revert_reason,
                'reverted_by'   => $user_id,
                'reverted_at'   => $now,
                'updated_at'    => $now
            ), array('id' => $id));

            // 5. Auditoría
            if (class_exists('Aura_Financial_Audit')) {
                Aura_Financial_Audit::log_action(
                    'currency_exchange_reverted',
                    'currency_exchange',
                    $id,
                    array(
                        'status'         => 'completed',
                        'source_balance' => floatval($source_acc->current_balance),
                        'target_balance' => floatval($target_acc->current_balance),
                    ),
                    array(
                        'status'             => 'reverted',
                        'source_new_balance' => $new_source_balance,
                        'target_new_balance' => $new_target_balance,
                        'revert_reason'      => $revert_reason,
                        'reverted_by'        => $user_id,
                        'reverted_at'        => $now,
                    )
                );
            }

            $wpdb->query('COMMIT');

            wp_send_json_success(array(
                'message' => __('Operación de cambio anulada y saldos revertidos exitosamente.', 'aura-suite')
            ));
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => __('Error al revertir la operación: ', 'aura-suite') . $e->getMessage()));
        }
    }

    public static function ajax_delete_exchange() {
        self::check_ajax_permissions('aura_finance_exchange_delete');

        global $wpdb;
        $exchanges_table = $wpdb->prefix . 'aura_finance_currency_exchanges';

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id <= 0) {
            wp_send_json_error(array('message' => __('ID de operación inválido.', 'aura-suite')));
        }

        $exchange = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$exchanges_table} WHERE id = %d", $id));
        if (!$exchange) {
            wp_send_json_error(array('message' => __('La operación no existe.', 'aura-suite')));
        }

        if ($exchange->status === 'completed') {
            wp_send_json_error(array(
                'message' => __('Para eliminar esta operación activa debes anularla/revertirla primero, asegurando la consistencia contable de los saldos.', 'aura-suite')
            ));
        }

        $wpdb->delete($exchanges_table, array('id' => $id));

        if (class_exists('Aura_Financial_Audit')) {
            Aura_Financial_Audit::log_action(
                'currency_exchange_deleted',
                'currency_exchange',
                $id,
                (array) $exchange,
                array('deleted_by' => get_current_user_id())
            );
        }

        wp_send_json_success(array('message' => __('Registro de cambio eliminado del historial.', 'aura-suite')));
    }

    public static function ajax_list_petty_cash_settlements() {
        self::check_ajax_permissions();
        self::migrate_settlements_phase3_columns();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_petty_cash_settlements';
        $accounts = $wpdb->prefix . 'aura_finance_accounts';
        $users = $wpdb->users;
        $third_parties = $wpdb->prefix . 'aura_finance_third_parties';

        // Verificación dinámica de la columna delivery_type para máxima resiliencia
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        $has_delivery_type = is_array($columns) && in_array('delivery_type', $columns, true);
        $delivery_type_select = $has_delivery_type
            ? "COALESCE(s.delivery_type, 'purchase_errand') AS delivery_type,"
            : "'purchase_errand' AS delivery_type,";

        $rows = $wpdb->get_results(
            "SELECT s.id, s.petty_cash_account_id, s.responsible_user_id, s.counterparty_id,
                    {$delivery_type_select}
                    s.delivered_amount, s.spent_amount, s.returned_amount,
                    s.status, s.due_date, s.evidence_json, s.notes, s.created_at, s.updated_at,
                    CASE
                        WHEN s.status IN ('open', 'submitted') AND s.due_date IS NOT NULL AND s.due_date < NOW() THEN 1
                        ELSE 0
                    END AS is_overdue,
                    COALESCE(a.name, 'Caja Chica') AS account_name,
                    COALESCE(u.display_name, tp.full_name, 'Responsable') AS responsible_name,
                    tp.logo_id AS tp_logo_id,
                    tp.wp_user_id AS tp_wp_user_id
             FROM {$table} s
             LEFT JOIN {$accounts} a ON a.id = s.petty_cash_account_id
             LEFT JOIN {$users} u ON u.ID = s.responsible_user_id
             LEFT JOIN {$third_parties} tp ON tp.id = s.counterparty_id
             WHERE (a.deleted_at IS NULL OR a.id IS NULL)
             ORDER BY s.created_at DESC, s.id DESC
             LIMIT 200",
            ARRAY_A
        );
        if (!is_array($rows)) {
            $rows = array();
        }

        $expenses_table = $wpdb->prefix . 'aura_finance_petty_cash_expenses';
        $categories_table = $wpdb->prefix . 'aura_finance_categories';
        $all_expenses = $wpdb->get_results(
            "SELECT e.*, c.name AS category_name 
             FROM {$expenses_table} e 
             LEFT JOIN {$categories_table} c ON c.id = e.category_id",
            ARRAY_A
        );
        $expenses_by_settlement = array();
        if ($all_expenses) {
            foreach ($all_expenses as $exp) {
                $expenses_by_settlement[$exp['settlement_id']][] = $exp;
            }
        }

        foreach ($rows as &$row) {
            // Resolve responsible avatar, name and value
            $avatar_url = '';
            if (!empty($row['counterparty_id'])) {
                $row['responsible_value'] = 'tp:' . $row['counterparty_id'];
                if (!empty($row['tp_logo_id'])) {
                    $logo_src = wp_get_attachment_image_url((int) $row['tp_logo_id'], 'thumbnail');
                    if ($logo_src) {
                        $avatar_url = $logo_src;
                    }
                }
                if (!$avatar_url && !empty($row['tp_wp_user_id'])) {
                    $avatar_url = get_avatar_url((int) $row['tp_wp_user_id'], array('size' => 64));
                }
            } elseif (!empty($row['responsible_user_id'])) {
                $row['responsible_value'] = 'wp:' . $row['responsible_user_id'];
                $avatar_url = get_avatar_url((int) $row['responsible_user_id'], array('size' => 64));
            } else {
                $row['responsible_value'] = '';
            }
            $row['responsible_avatar_url'] = $avatar_url;
            
            // Attach expenses
            $row['expenses'] = isset($expenses_by_settlement[$row['id']]) ? $expenses_by_settlement[$row['id']] : array();
        }
        unset($row);

        $petty_cash_accounts = $wpdb->get_results(
            "SELECT id, name, currency, current_balance
             FROM {$accounts}
             WHERE deleted_at IS NULL AND is_active = 1 AND account_type = 'petty_cash'
             ORDER BY name ASC",
            ARRAY_A
        );

        $all_accounts = $wpdb->get_results(
            "SELECT id, name, currency, current_balance, account_type
             FROM {$accounts}
             WHERE deleted_at IS NULL AND is_active = 1
             ORDER BY name ASC",
            ARRAY_A
        );

        wp_send_json_success(array(
            'settlements' => $rows,
            'petty_cash_accounts' => $petty_cash_accounts,
            'all_accounts' => $all_accounts,
            'can_approve' => (current_user_can('aura_finance_petty_cash_approve') || current_user_can('aura_finance_approve') || current_user_can('manage_options')),
        ));
    }

    public static function ajax_create_petty_cash_settlement() {
        self::check_ajax_permissions();
        self::migrate_settlements_phase3_columns();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_petty_cash_settlements';

        $petty_cash_account_id = absint($_POST['petty_cash_account_id'] ?? 0);
        $origin_account_id = absint($_POST['origin_account_id'] ?? 0);
        
        $responsible_input = sanitize_text_field($_POST['responsible_user_id'] ?? '');
        $responsible_user_id = null;
        $counterparty_id = null;
        
        if (strpos($responsible_input, 'wp:') === 0) {
            $responsible_user_id = absint(substr($responsible_input, 3));
        } elseif (strpos($responsible_input, 'tp:') === 0) {
            $counterparty_id = absint(substr($responsible_input, 3));
        } else {
            // Backward compatibility
            $responsible_user_id = absint($responsible_input);
        }
        
        $delivered_amount = isset($_POST['delivered_amount']) ? (float) $_POST['delivered_amount'] : 0;
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

        if ($petty_cash_account_id <= 0) {
            wp_send_json_error(array('message' => __('Debes seleccionar una cuenta de caja chica.', 'aura-suite')));
        }

        $account = self::get_account_by_id($petty_cash_account_id);
        if (!$account || $account->account_type !== 'petty_cash') {
            wp_send_json_error(array('message' => __('La cuenta seleccionada no corresponde a caja chica.', 'aura-suite')));
        }

        if ((!$responsible_user_id && !$counterparty_id) || 
            ($responsible_user_id && !get_user_by('id', $responsible_user_id)) || 
            ($counterparty_id && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}aura_finance_third_parties WHERE id = %d", $counterparty_id)))) {
            wp_send_json_error(array('message' => __('Debes indicar un responsable válido.', 'aura-suite')));
        }

        if ($delivered_amount <= 0) {
            wp_send_json_error(array('message' => __('El monto entregado debe ser mayor a cero.', 'aura-suite')));
        }

        // Subida opcional o URL directa de comprobante de entrega/transferencia inicial
        $delivery_receipt_url = null;
        if (!empty($_FILES['delivery_receipt_file']) && !empty($_FILES['delivery_receipt_file']['name'])) {
            $upload_res = self::handle_single_receipt_upload('delivery_receipt_file', 'Caja Chica', current_time('Y-m-d'));
            if (is_wp_error($upload_res)) {
                wp_send_json_error(array('message' => $upload_res->get_error_message()));
            }
            $delivery_receipt_url = $upload_res;
        } elseif (!empty($_POST['delivery_receipt_url'])) {
            $url_clean = esc_url_raw(trim(wp_unslash($_POST['delivery_receipt_url'])));
            if (!empty($url_clean)) {
                $delivery_receipt_url = $url_clean;
            }
        }

        // Si en las notas se incluyó una URL de comprobante (ej: Drive o recibo pegado), extraerla
        if (!$delivery_receipt_url && preg_match('/https?:\/\/[^\s,;]+/i', $notes, $matched_url)) {
            $delivery_receipt_url = esc_url_raw($matched_url[0]);
        }

        $evidence_payload = null;
        if ($delivery_receipt_url) {
            $evidence_payload = wp_json_encode(array(
                'delivery_receipt' => $delivery_receipt_url,
                'attachments' => array(
                    array(
                        'id' => 0,
                        'url' => $delivery_receipt_url,
                        'name' => __('Comprobante de Entrega / Transferencia Inicial', 'aura-suite'),
                    )
                )
            ));
        }

        $delivery_type = sanitize_text_field($_POST['delivery_type'] ?? 'purchase_errand');
        if (!in_array($delivery_type, array('purchase_errand', 'program_budget'), true)) {
            $delivery_type = 'purchase_errand';
        }

        $insert_data = array(
            'petty_cash_account_id' => $petty_cash_account_id,
            'responsible_user_id' => $responsible_user_id,
            'counterparty_id' => $counterparty_id,
            'delivered_amount' => $delivered_amount,
            'spent_amount' => 0,
            'returned_amount' => 0,
            'status' => 'open',
            'due_date' => self::resolve_petty_cash_due_date(wp_unslash($_POST['due_date'] ?? ''), $delivery_type),
            'evidence_json' => $evidence_payload,
            'notes' => $notes,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );
        $insert_formats = array('%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s', '%s');

        $cols_check = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (is_array($cols_check) && in_array('delivery_type', $cols_check, true)) {
            $insert_data['delivery_type'] = $delivery_type;
            $insert_formats[] = '%s';
        }

        $ok = $wpdb->insert($table, $insert_data, $insert_formats);

        if (!$ok) {
            wp_send_json_error(array('message' => __('No se pudo crear la rendición de caja chica.', 'aura-suite')));
        }

        $settlement_id = (int) $wpdb->insert_id;

        // Si se especificó cuenta bancaria de origen para el fondeo, registrar los movimientos contables
        if ($origin_account_id > 0) {
            $origin_acc = self::get_account_by_id($origin_account_id);
            if ($origin_acc) {
                // Egreso/salida de fondos del banco
                self::register_account_movement(array(
                    'account_id'     => $origin_account_id,
                    'movement_type'  => 'transfer_out',
                    'amount'         => $delivered_amount,
                    'reference_type' => 'petty_cash_settlement',
                    'reference_id'   => $settlement_id,
                    'notes'          => sprintf(__('Fondeo entregado a Caja Chica (%s) - Entrega #%d', 'aura-suite'), $account->name, $settlement_id),
                ));
                // Ingreso/entrada de fondos a la cuenta de caja chica
                self::register_account_movement(array(
                    'account_id'     => $petty_cash_account_id,
                    'movement_type'  => 'transfer_in',
                    'amount'         => $delivered_amount,
                    'reference_type' => 'petty_cash_settlement',
                    'reference_id'   => $settlement_id,
                    'notes'          => sprintf(__('Fondeo recibido desde %s - Entrega #%d', 'aura-suite'), $origin_acc->name, $settlement_id),
                ));
            }
        }

        wp_send_json_success(array(
            'message' => __('Entrega de fondo registrada en estado Abierta.', 'aura-suite'),
            'id' => $settlement_id,
        ));
    }

    public static function ajax_delete_petty_cash_settlement() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_petty_cash_settlements';
        $expenses_table = $wpdb->prefix . 'aura_finance_petty_cash_expenses';

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        if ($id <= 0) {
            wp_send_json_error(array('message' => __('ID inválido.', 'aura-suite')));
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT status FROM {$table} WHERE id = %d", $id));
        
        if (!$row) {
            wp_send_json_error(array('message' => __('El registro no existe.', 'aura-suite')));
        }

        if (in_array($row->status, array('approved', 'closed'))) {
            wp_send_json_error(array('message' => __('No se pueden eliminar rendiciones que ya han sido aprobadas o cerradas.', 'aura-suite')));
        }

        $wpdb->query('START TRANSACTION');

        try {
            $wpdb->delete($expenses_table, array('settlement_id' => $id), array('%d'));
            $wpdb->delete($table, array('id' => $id), array('%d'));
            $wpdb->query('COMMIT');
            wp_send_json_success(array('message' => __('Registro de caja chica eliminado correctamente.', 'aura-suite')));
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => __('Error al eliminar el registro.', 'aura-suite')));
        }
    }

    public static function ajax_submit_petty_cash_settlement() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_petty_cash_settlements';
        $id = absint($_POST['id'] ?? 0);

        if ($id <= 0) {
            wp_send_json_error(array('message' => __('ID de rendición inválido.', 'aura-suite')));
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$row) {
            wp_send_json_error(array('message' => __('Rendición no encontrada.', 'aura-suite')));
        }

        if (!in_array($row->status, array('open', 'rejected'), true)) {
            wp_send_json_error(array('message' => __('Solo puedes enviar rendiciones abiertas o rechazadas.', 'aura-suite')));
        }

        $expenses_json = wp_unslash($_POST['expenses_json'] ?? '[]');
        $expenses = json_decode($expenses_json, true);
        
        if (!is_array($expenses) || empty($expenses)) {
            wp_send_json_error(array('message' => __('Debes registrar al menos un gasto para rendir.', 'aura-suite')));
        }

        $spent_amount = 0;
        foreach ($expenses as $exp) {
            $spent_amount += (float) ($exp['amount'] ?? 0);
        }

        $delivered = (float) $row->delivered_amount;
        $returned_amount = isset($_POST['returned_amount']) ? (float) $_POST['returned_amount'] : 0;
        
        if ($spent_amount < 0 || $returned_amount < 0) {
            wp_send_json_error(array('message' => __('Los montos no pueden ser negativos.', 'aura-suite')));
        }

        if ($spent_amount > $delivered) {
            $returned_amount = 0; // Excedente a favor del responsable
        } else {
            $diff = abs(($spent_amount + $returned_amount) - $delivered);
            if ($diff > 0.01) {
                wp_send_json_error(array('message' => __('La regla exige: Entregado = Gastado + Devuelto.', 'aura-suite')));
            }
        }

        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        $evidence_text = sanitize_textarea_field(wp_unslash($_POST['evidence_json'] ?? ''));

        $upload_result = self::handle_petty_cash_evidence_uploads('evidence_files');
        if (is_wp_error($upload_result)) {
            wp_send_json_error(array('message' => $upload_result->get_error_message()));
        }

        $evidence_payload = array(
            'links' => $evidence_text,
            'attachments' => $upload_result,
        );

        $ok = $wpdb->update(
            $table,
            array(
                'category_id' => null, // Ya no se usa a nivel general
                'spent_amount' => $spent_amount,
                'returned_amount' => $returned_amount,
                'status' => 'submitted',
                'evidence_json' => (!empty($evidence_text) || !empty($upload_result)) ? wp_json_encode($evidence_payload) : null,
                'notes' => $notes,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $id),
            array('%d', '%f', '%f', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($ok === false) {
            wp_send_json_error(array('message' => __('No se pudo enviar la rendición.', 'aura-suite')));
        }

        // Insertar gastos individuales
        $expenses_table = $wpdb->prefix . 'aura_finance_petty_cash_expenses';
        $wpdb->query($wpdb->prepare("DELETE FROM {$expenses_table} WHERE settlement_id = %d", $id));

        foreach ($expenses as $exp) {
            $amt = (float) ($exp['amount'] ?? 0);
            if ($amt <= 0) continue;
            
            $cat_id = absint($exp['category_id'] ?? 0);
            $concept = sanitize_text_field($exp['notes'] ?? $exp['concept'] ?? $exp['receipt_url'] ?? '');
            
            $wpdb->insert(
                $expenses_table,
                array(
                    'settlement_id' => $id,
                    'category_id'   => $cat_id > 0 ? $cat_id : null,
                    'amount'        => $amt,
                    'receipt_url'   => sanitize_text_field($exp['receipt_url'] ?? ''),
                    'notes'         => $concept,
                    'created_at'    => current_time('mysql'),
                ),
                array('%d', '%d', '%f', '%s', '%s', '%s')
            );
        }

        wp_send_json_success(array('message' => __('Rendición enviada para aprobación.', 'aura-suite')));
    }

    public static function ajax_update_petty_cash_status() {
        self::check_ajax_permissions();

        if (!(current_user_can('aura_finance_petty_cash_approve') || current_user_can('aura_finance_approve') || current_user_can('manage_options'))) {
            wp_send_json_error(array('message' => __('No tienes permisos para aprobar/cerrar rendiciones.', 'aura-suite')), 403);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_petty_cash_settlements';

        $id = absint($_POST['id'] ?? 0);
        $new_status = sanitize_key(wp_unslash($_POST['status'] ?? ''));
        $allowed = array('approved', 'closed', 'rejected');

        if ($id <= 0 || !in_array($new_status, $allowed, true)) {
            wp_send_json_error(array('message' => __('Solicitud de estado inválida.', 'aura-suite')));
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$row) {
            wp_send_json_error(array('message' => __('Rendición no encontrada.', 'aura-suite')));
        }

        $current = (string) $row->status;
        $allowed_transition = (
            ($current === 'submitted' && in_array($new_status, array('approved', 'rejected'), true)) ||
            ($current === 'approved' && $new_status === 'closed')
        );

        if (!$allowed_transition) {
            wp_send_json_error(array('message' => __('Transición de estado no permitida.', 'aura-suite')));
        }

        $data = array(
            'status' => $new_status,
            'updated_at' => current_time('mysql'),
        );
        $format = array('%s', '%s');

        if ($new_status === 'approved') {
            $data['approved_by'] = get_current_user_id();
            $data['approved_at'] = current_time('mysql');
            $format[] = '%d';
            $format[] = '%s';
        }

        $ok = $wpdb->update($table, $data, array('id' => $id), $format, array('%d'));
        if ($ok === false) {
            wp_send_json_error(array('message' => __('No se pudo actualizar el estado.', 'aura-suite')));
        }

        $labels = array(
            'approved' => __('Aprobada', 'aura-suite'),
            'closed' => __('Cerrada', 'aura-suite'),
            'rejected' => __('Rechazada', 'aura-suite'),
        );

        if ($new_status === 'approved' && $row->spent_amount > 0) {
            $expenses_table = $wpdb->prefix . 'aura_finance_petty_cash_expenses';
            $expenses = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$expenses_table} WHERE settlement_id = %d", $id));

            $last_tx_id = null;

            // Extraer comprobante general si existe
            $general_evidence = null;
            if (!empty($row->evidence_json)) {
                $ev_data = json_decode($row->evidence_json, true);
                if (is_array($ev_data)) {
                    if (!empty($ev_data['delivery_receipt'])) {
                        $general_evidence = $ev_data['delivery_receipt'];
                    } elseif (!empty($ev_data['attachments'][0]['url'])) {
                        $general_evidence = $ev_data['attachments'][0]['url'];
                    } elseif (!empty($ev_data['links'])) {
                        $general_evidence = $ev_data['links'];
                    }
                }
            }

            if (!empty($expenses)) {
                foreach ($expenses as $exp) {
                    if ($exp->amount <= 0 || !$exp->category_id) continue;
                    
                    $cat = $wpdb->get_row($wpdb->prepare("SELECT is_capex, name FROM {$wpdb->prefix}aura_finance_categories WHERE id = %d", $exp->category_id));
                    $is_capex = ($cat && $cat->is_capex == 1);
                    $tx_type = $is_capex ? 'capital' : 'expense';

                    $exp_receipt = !empty($exp->receipt_url) ? $exp->receipt_url : $general_evidence;

                    $transaction_data = array(
                        'transaction_type'   => $tx_type,
                        'category_id'        => $exp->category_id,
                        'amount'             => $exp->amount,
                        'transaction_date'   => current_time('Y-m-d'),
                        'description'        => sprintf(__('Rendición Caja Chica #%d - %s', 'aura-suite'), $id, $cat ? $cat->name : ''),
                        'notes'              => $row->notes . ($exp_receipt ? "\n" . sprintf(__('Evidencia: %s', 'aura-suite'), $exp_receipt) : ""),
                        'status'             => 'approved',
                        'payment_method'     => 'Efectivo',
                        'source_account_id'  => $row->petty_cash_account_id,
                        'counterparty_id'    => $row->counterparty_id > 0 ? (int) $row->counterparty_id : null,
                        'user_id'            => $row->responsible_user_id > 0 ? (int) $row->responsible_user_id : null,
                        'concept_link_id'    => 'petty_cash_settlement',
                        'business_reason'    => sprintf(__('Rendición de fondos Caja Chica #%d', 'aura-suite'), $id),
                        'receipt_path'       => $exp_receipt,
                        'receipt_file'       => $exp_receipt,
                        'created_by'         => get_current_user_id(),
                        'approved_by'        => get_current_user_id(),
                        'approved_at'        => current_time('mysql'),
                        'created_at'         => current_time('mysql'),
                        'updated_at'         => current_time('mysql'),
                    );
                    
                    $wpdb->insert($wpdb->prefix . 'aura_finance_transactions', $transaction_data);
                    $tx_id = $wpdb->insert_id;
                    if ($tx_id) {
                        $last_tx_id = $tx_id;
                        self::register_account_movement(array(
                            'account_id' => $row->petty_cash_account_id,
                            'transaction_id' => $tx_id,
                            'movement_type' => 'debit',
                            'amount' => $exp->amount,
                            'reference_type' => 'petty_cash_settlement',
                            'reference_id' => $id,
                            'notes' => __('Gasto individual registrado desde Rendición.', 'aura-suite'),
                        ));
                    }
                }
            } elseif ($row->category_id > 0) {
                // Compatibilidad hacia atrás
                $cat = $wpdb->get_row($wpdb->prepare("SELECT is_capex, name FROM {$wpdb->prefix}aura_finance_categories WHERE id = %d", $row->category_id));
                $is_capex = ($cat && $cat->is_capex == 1);
                $tx_type = $is_capex ? 'capital' : 'expense';

                $transaction_data = array(
                    'transaction_type'   => $tx_type,
                    'category_id'        => $row->category_id,
                    'amount'             => $row->spent_amount,
                    'transaction_date'   => current_time('Y-m-d'),
                    'description'        => sprintf(__('Rendición de Caja Chica (Ref: #%d) - %s', 'aura-suite'), $id, $cat ? $cat->name : ''),
                    'notes'              => $row->notes . ($general_evidence ? "\n" . sprintf(__('Evidencia: %s', 'aura-suite'), $general_evidence) : ""),
                    'status'             => 'approved',
                    'payment_method'     => 'Efectivo',
                    'source_account_id'  => $row->petty_cash_account_id,
                    'counterparty_id'    => $row->counterparty_id > 0 ? (int) $row->counterparty_id : null,
                    'user_id'            => $row->responsible_user_id > 0 ? (int) $row->responsible_user_id : null,
                    'concept_link_id'    => 'petty_cash_settlement',
                    'business_reason'    => sprintf(__('Rendición de fondos Caja Chica #%d', 'aura-suite'), $id),
                    'receipt_path'       => $general_evidence,
                    'receipt_file'       => $general_evidence,
                    'created_by'         => get_current_user_id(),
                    'approved_by'        => get_current_user_id(),
                    'approved_at'        => current_time('mysql'),
                    'created_at'         => current_time('mysql'),
                    'updated_at'         => current_time('mysql'),
                );
                
                $wpdb->insert($wpdb->prefix . 'aura_finance_transactions', $transaction_data);
                $tx_id = $wpdb->insert_id;

                if ($tx_id) {
                    $last_tx_id = $tx_id;
                    self::register_account_movement(array(
                        'account_id' => $row->petty_cash_account_id,
                        'transaction_id' => $tx_id,
                        'movement_type' => 'debit',
                        'amount' => $row->spent_amount,
                        'reference_type' => 'petty_cash_settlement',
                        'reference_id' => $id,
                        'notes' => __('Gasto registrado desde Rendición.', 'aura-suite'),
                    ));
                }
            }
        }

        // Si gastó más de lo entregado, crear reembolso (reimbursement) al responsable.
        if ($new_status === 'approved' && $row->spent_amount > $row->delivered_amount) {
            $excedent = $row->spent_amount - $row->delivered_amount;
            $wpdb->insert(
                $wpdb->prefix . 'aura_finance_reimbursements',
                array(
                    'person_user_id' => $row->responsible_user_id,
                    'counterparty_id' => $row->counterparty_id,
                    'origin_transaction_id' => $last_tx_id ?? null,
                    'owed_amount' => $excedent,
                    'paid_amount' => 0,
                    'status' => 'pending',
                    'notes' => sprintf(__('Reembolso automático por excedente en Rendición de Caja Chica #%d.', 'aura-suite'), $id),
                    'created_by' => get_current_user_id(),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                )
            );
        }

        wp_send_json_success(array('message' => sprintf(__('Rendición actualizada a: %s.', 'aura-suite'), $labels[$new_status])));
    }

    public static function ajax_list_reimbursements() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_reimbursements';
        $tx_table = $wpdb->prefix . 'aura_finance_transactions';
        $accounts = $wpdb->prefix . 'aura_finance_accounts';
        $third_parties = $wpdb->prefix . 'aura_finance_third_parties';
        $users = $wpdb->users;

        $rows = $wpdb->get_results(
                "SELECT r.id, r.person_user_id, r.counterparty_id, r.origin_transaction_id, r.owed_amount, r.paid_amount,
                    r.status, r.paying_account_id, r.notes, r.created_at, r.updated_at,
                    COALESCE(tp.full_name, u.display_name) AS person_name,
                    tp.document_id AS person_document,
                    a.name AS paying_account_name,
                    t.description AS origin_description,
                    t.transaction_date AS origin_date
             FROM {$table} r
                 LEFT JOIN {$third_parties} tp ON tp.id = r.counterparty_id
             LEFT JOIN {$users} u ON u.ID = r.person_user_id
             LEFT JOIN {$accounts} a ON a.id = r.paying_account_id
             LEFT JOIN {$tx_table} t ON t.id = r.origin_transaction_id
             ORDER BY FIELD(r.status, 'pending', 'partial', 'paid', 'cancelled'), r.created_at DESC, r.id DESC
             LIMIT 300",
            ARRAY_A
        );

        $paying_accounts = $wpdb->get_results(
            "SELECT id, name, currency, current_balance
             FROM {$accounts}
             WHERE deleted_at IS NULL AND is_active = 1
             ORDER BY name ASC",
            ARRAY_A
        );

        wp_send_json_success(array(
            'reimbursements' => $rows,
            'paying_accounts' => $paying_accounts,
            'third_parties' => self::get_active_third_parties(),
            'users' => self::get_app_users_for_reimbursements(),
        ));
    }

    public static function ajax_list_third_parties() {
        self::check_ajax_permissions();

        $include_inactive = !empty($_POST['include_inactive']);

        wp_send_json_success(array(
            'third_parties' => self::get_third_parties($include_inactive),
        ));
    }

    public static function ajax_search_third_parties() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_third_parties';

        $term = sanitize_text_field(wp_unslash($_POST['term'] ?? ''));
        if (mb_strlen($term) < 1) {
            wp_send_json_success(array());
        }

        $search = '%' . $wpdb->esc_like($term) . '%';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, full_name, commercial_name, party_type, document_id, tax_id_type, phone, email, logo_id
             FROM {$table}
             WHERE is_active = 1
               AND (full_name LIKE %s OR commercial_name LIKE %s OR document_id LIKE %s OR email LIKE %s)
             ORDER BY full_name ASC
             LIMIT 20",
            $search,
            $search,
            $search,
            $search
        ), ARRAY_A);

        $results = array();
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

        foreach ($rows as $r) {
            $logo_id = (int) ($r['logo_id'] ?? 0);
            $logo_url = $logo_id ? (wp_get_attachment_image_url($logo_id, 'thumbnail') ?: wp_get_attachment_url($logo_id) ?: '') : '';
            $ptype = $r['party_type'] ?: 'company';
            $display_name = !empty($r['commercial_name']) ? ($r['commercial_name'] . ' (' . $r['full_name'] . ')') : $r['full_name'];

            $results[] = array(
                'id'              => (int) $r['id'],
                'full_name'       => $r['full_name'],
                'commercial_name' => $r['commercial_name'] ?: '',
                'display_name'    => $display_name,
                'party_type'      => $ptype,
                'party_type_label'=> $type_labels[$ptype] ?? $type_labels['company'],
                'icon'            => $type_icons[$ptype] ?? 'dashicons-building',
                'document_id'     => $r['document_id'] ?: '',
                'tax_id_type'     => $r['tax_id_type'] ?: 'NIT',
                'phone'           => $r['phone'] ?: '',
                'email'           => $r['email'] ?: '',
                'logo_id'         => $logo_id,
                'logo_url'        => $logo_url,
                'label'           => $display_name . ($r['document_id'] ? ' - ' . ($r['tax_id_type'] ?: 'Doc') . ': ' . $r['document_id'] : ''),
                'value'           => $r['commercial_name'] ?: $r['full_name'],
            );
        }

        wp_send_json_success($results);
    }

    public static function ajax_create_third_party() {
        self::check_ajax_permissions();

        $full_name = sanitize_text_field(wp_unslash($_POST['full_name'] ?? ''));
        $commercial_name = sanitize_text_field(wp_unslash($_POST['commercial_name'] ?? ''));
        $party_type = sanitize_key(wp_unslash($_POST['party_type'] ?? 'company'));
        $document_id = sanitize_text_field(wp_unslash($_POST['document_id'] ?? ''));
        $tax_id_type = sanitize_text_field(wp_unslash($_POST['tax_id_type'] ?? 'NIT'));
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $website = esc_url_raw(wp_unslash($_POST['website'] ?? ''));
        $address = sanitize_textarea_field(wp_unslash($_POST['address'] ?? ''));
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        $logo_id = absint($_POST['logo_id'] ?? 0);

        if (!in_array($party_type, array('company', 'store', 'organization_foundation', 'person', 'religious', 'other'), true)) {
            $party_type = 'company';
        }

        if ($full_name === '') {
            wp_send_json_error(array('message' => __('Debes indicar el nombre o razón social.', 'aura-suite')));
        }

        if ($email !== '' && !is_email($email)) {
            wp_send_json_error(array('message' => __('El correo no es válido.', 'aura-suite')));
        }

        $counterparty_id = self::ensure_third_party(
            $full_name,
            array(
                'commercial_name' => $commercial_name,
                'party_type'      => $party_type,
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

        if ($counterparty_id <= 0) {
            wp_send_json_error(array('message' => __('No se pudo registrar el tercero/empresa.', 'aura-suite')));
        }

        $logo_url = $logo_id > 0 ? (wp_get_attachment_image_url($logo_id, 'thumbnail') ?: wp_get_attachment_url($logo_id) ?: '') : '';

        wp_send_json_success(array(
            'id' => $counterparty_id,
            'full_name' => $full_name,
            'commercial_name' => $commercial_name,
            'party_type' => $party_type,
            'logo_id' => $logo_id,
            'logo_url' => $logo_url,
            'message' => __('Tercero / Empresa registrado correctamente.', 'aura-suite'),
            'third_parties' => self::get_active_third_parties(),
        ));
    }

    public static function ajax_update_third_party() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_third_parties';

        $id = absint($_POST['id'] ?? 0);
        $full_name = sanitize_text_field(wp_unslash($_POST['full_name'] ?? ''));
        $commercial_name = sanitize_text_field(wp_unslash($_POST['commercial_name'] ?? ''));
        $party_type = sanitize_key(wp_unslash($_POST['party_type'] ?? 'company'));
        $document_id = sanitize_text_field(wp_unslash($_POST['document_id'] ?? ''));
        $tax_id_type = sanitize_text_field(wp_unslash($_POST['tax_id_type'] ?? 'NIT'));
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $website = esc_url_raw(wp_unslash($_POST['website'] ?? ''));
        $address = sanitize_textarea_field(wp_unslash($_POST['address'] ?? ''));
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        $logo_id = absint($_POST['logo_id'] ?? 0);

        if (!in_array($party_type, array('company', 'store', 'organization_foundation', 'person', 'religious', 'other'), true)) {
            $party_type = 'company';
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
                'commercial_name' => $commercial_name,
                'party_type'      => $party_type,
                'document_id'     => $document_id,
                'tax_id_type'     => $tax_id_type,
                'phone'           => $phone,
                'email'           => $email,
                'website'         => $website,
                'address'         => $address,
                'notes'           => $notes,
                'logo_id'         => $logo_id > 0 ? $logo_id : null,
                'updated_at'      => current_time('mysql'),
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );

        if ($ok === false) {
            wp_send_json_error(array('message' => __('No se pudo actualizar el tercero.', 'aura-suite')));
        }

        wp_send_json_success(array(
            'message' => __('Tercero actualizado correctamente.', 'aura-suite'),
            'third_parties' => self::get_third_parties(true),
        ));
    }

    public static function ajax_toggle_third_party() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_third_parties';

        $id = absint($_POST['id'] ?? 0);
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : -1;

        if ($id <= 0 || !in_array($is_active, array(0, 1), true)) {
            wp_send_json_error(array('message' => __('Datos inválidos para actualizar el tercero.', 'aura-suite')));
        }

        $ok = $wpdb->update(
            $table,
            array(
                'is_active' => $is_active,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $id),
            array('%d', '%s'),
            array('%d')
        );

        if ($ok === false) {
            wp_send_json_error(array('message' => __('No se pudo cambiar el estado del tercero.', 'aura-suite')));
        }

        wp_send_json_success(array(
            'message' => $is_active === 1
                ? __('Tercero reactivado correctamente.', 'aura-suite')
                : __('Tercero desactivado correctamente.', 'aura-suite'),
            'third_parties' => self::get_third_parties(true),
        ));
    }

    public static function ajax_convert_third_party_to_user() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_third_parties';

        $id = absint($_POST['id'] ?? 0);
        $role = sanitize_key(wp_unslash($_POST['role'] ?? 'subscriber'));
        $send_invite = !empty($_POST['send_invite']);

        if ($id <= 0) {
            wp_send_json_error(array('message' => __('Tercero inválido para convertir.', 'aura-suite')));
        }

        $third_party = $wpdb->get_row($wpdb->prepare(
            "SELECT id, full_name, email, wp_user_id, is_active FROM {$table} WHERE id = %d LIMIT 1",
            $id
        ), ARRAY_A);

        if (!$third_party) {
            wp_send_json_error(array('message' => __('No se encontró el tercero.', 'aura-suite')));
        }

        $existing_user_id = absint($third_party['wp_user_id'] ?? 0);
        if ($existing_user_id > 0 && get_user_by('id', $existing_user_id)) {
            wp_send_json_success(array(
                'message' => __('Este tercero ya está vinculado a un usuario de WordPress.', 'aura-suite'),
                'user_id' => $existing_user_id,
                'third_parties' => self::get_third_parties(true),
            ));
        }

        $email = sanitize_email((string) ($third_party['email'] ?? ''));
        if ($email === '' || !is_email($email)) {
            wp_send_json_error(array('message' => __('Debes registrar un correo válido en el tercero antes de convertirlo a usuario WP.', 'aura-suite')));
        }

        $user = get_user_by('email', $email);
        if ($user) {
            $user_id = (int) $user->ID;
        } else {
            $base_login = sanitize_user(remove_accents((string) $third_party['full_name']), true);
            $base_login = trim(preg_replace('/\s+/', '', $base_login));
            if ($base_login === '') {
                $base_login = 'tercero' . $id;
            }

            $login = $base_login;
            $suffix = 1;
            while (username_exists($login)) {
                $suffix++;
                $login = $base_login . $suffix;
            }

            $user_id = wp_create_user($login, wp_generate_password(18, true, true), $email);
            if (is_wp_error($user_id)) {
                wp_send_json_error(array('message' => $user_id->get_error_message()));
            }

            wp_update_user(array(
                'ID' => (int) $user_id,
                'display_name' => sanitize_text_field((string) $third_party['full_name']),
            ));

            if (function_exists('wp_roles')) {
                $roles = wp_roles();
                if (!$roles || !isset($roles->roles[$role])) {
                    $role = 'subscriber';
                }
            }
            $user_obj = get_user_by('id', $user_id);
            if ($user_obj) {
                $user_obj->set_role($role);
            }

            if ($send_invite && function_exists('wp_send_new_user_notifications')) {
                wp_send_new_user_notifications((int) $user_id, 'user');
            }
        }

        $ok = $wpdb->update(
            $table,
            array(
                'wp_user_id' => (int) $user_id,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $id),
            array('%d', '%s'),
            array('%d')
        );

        if ($ok === false) {
            wp_send_json_error(array('message' => __('Se creó/vinculó el usuario, pero no se pudo guardar el vínculo con el tercero.', 'aura-suite')));
        }

        wp_send_json_success(array(
            'message' => __('Tercero vinculado correctamente con usuario WordPress.', 'aura-suite'),
            'user_id' => (int) $user_id,
            'third_parties' => self::get_third_parties(true),
        ));
    }

    public static function ajax_get_accounts_report() {
        self::check_ajax_permissions();

        $year = isset($_POST['year']) ? absint($_POST['year']) : (int) current_time('Y');
        if ($year < 2000 || $year > 2100) {
            $year = (int) current_time('Y');
        }

        wp_send_json_success(self::build_accounts_report_data($year));
    }

    public static function ajax_create_reimbursement() {
        self::check_ajax_permissions();

        $person_input = sanitize_text_field(wp_unslash($_POST['person_id'] ?? $_POST['counterparty_id'] ?? ''));
        $counterparty_id = absint($_POST['counterparty_id'] ?? 0);
        $person_user_id = absint($_POST['person_user_id'] ?? 0);

        if (strpos($person_input, 'wp:') === 0) {
            $person_user_id = absint(substr($person_input, 3));
            $counterparty_id = 0;
        } elseif (strpos($person_input, 'tp:') === 0) {
            $counterparty_id = absint(substr($person_input, 3));
            $person_user_id = 0;
        }

        $owed_amount = isset($_POST['owed_amount']) ? (float) $_POST['owed_amount'] : 0;
        $origin_transaction_id = absint($_POST['origin_transaction_id'] ?? 0);
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

        if ($counterparty_id <= 0 && $person_user_id > 0) {
            $person = get_user_by('id', $person_user_id);
            if ($person) {
                $counterparty_id = self::ensure_third_party(
                    (string) $person->display_name,
                    array(
                        'email' => (string) $person->user_email,
                        'wp_user_id' => $person_user_id,
                    ),
                    get_current_user_id()
                );
            }
        }

        if (($counterparty_id <= 0 && $person_user_id <= 0) || ($counterparty_id > 0 && !self::get_third_party_by_id($counterparty_id) && $person_user_id <= 0)) {
            wp_send_json_error(array('message' => __('Debes seleccionar un usuario o tercero válido para el reembolso.', 'aura-suite')));
        }

        if ($owed_amount <= 0) {
            wp_send_json_error(array('message' => __('El valor adeudado debe ser mayor a cero.', 'aura-suite')));
        }

        $inserted_id = self::create_reimbursement(array(
            'counterparty_id' => $counterparty_id,
            'person_user_id' => $person_user_id,
            'origin_transaction_id' => $origin_transaction_id > 0 ? $origin_transaction_id : null,
            'owed_amount' => $owed_amount,
            'notes' => $notes,
            'created_by' => get_current_user_id(),
        ));

        if (!$inserted_id) {
            wp_send_json_error(array('message' => __('No se pudo registrar la deuda de reembolso.', 'aura-suite')));
        }

        wp_send_json_success(array(
            'id' => (int) $inserted_id,
            'message' => __('Deuda de reembolso registrada correctamente.', 'aura-suite'),
        ));
    }

    public static function ajax_pay_reimbursement() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_reimbursements';

        $id = absint($_POST['id'] ?? 0);
        $paying_account_id = absint($_POST['paying_account_id'] ?? 0);
        $payment_amount = isset($_POST['payment_amount']) ? (float) $_POST['payment_amount'] : 0;
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        $payment_date = !empty($_POST['payment_date']) ? sanitize_text_field($_POST['payment_date']) : current_time('Y-m-d');
        $payment_method = !empty($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : 'transferencia';
        $create_tx = !empty($_POST['create_transaction']);
        $category_id = absint($_POST['category_id'] ?? 0);
        $area_id = absint($_POST['area_id'] ?? 0);
        $concept = !empty($_POST['concept']) ? sanitize_text_field(wp_unslash($_POST['concept'])) : '';

        if ($paying_account_id <= 0 || $payment_amount <= 0) {
            wp_send_json_error(array('message' => __('Datos inválidos para registrar el pago del reembolso.', 'aura-suite')));
        }

        // Si es pago directo ($id <= 0), crear la deuda de reembolso con su beneficiario
        if ($id <= 0) {
            $person_input = sanitize_text_field(wp_unslash($_POST['person_id'] ?? $_POST['counterparty_id'] ?? ''));
            $counterparty_id = 0;
            $person_user_id = 0;

            if (strpos($person_input, 'wp:') === 0) {
                $person_user_id = absint(substr($person_input, 3));
            } elseif (strpos($person_input, 'tp:') === 0) {
                $counterparty_id = absint(substr($person_input, 3));
            } else {
                if (is_numeric($person_input) && (int) $person_input > 0) {
                    $counterparty_id = absint($person_input);
                } elseif (!empty($person_input)) {
                    $counterparty_id = self::ensure_third_party($person_input, array(), get_current_user_id());
                }
            }

            if ($counterparty_id <= 0 && $person_user_id > 0) {
                $person = get_user_by('id', $person_user_id);
                if ($person) {
                    $counterparty_id = self::ensure_third_party(
                        (string) $person->display_name,
                        array('email' => (string) $person->user_email, 'wp_user_id' => $person_user_id),
                        get_current_user_id()
                    );
                }
            }

            if ($counterparty_id <= 0 && $person_user_id <= 0) {
                wp_send_json_error(array('message' => __('Debes seleccionar o escribir un beneficiario válido para el pago directo.', 'aura-suite')));
            }

            $ok_r = $wpdb->insert(
                $table,
                array(
                    'counterparty_id'       => $counterparty_id > 0 ? $counterparty_id : null,
                    'person_user_id'        => $person_user_id > 0 ? $person_user_id : null,
                    'owed_amount'           => $payment_amount,
                    'paid_amount'           => 0,
                    'status'                => 'pending',
                    'origin_transaction_id' => null,
                    'notes'                 => $notes !== '' ? $notes : __('Pago directo de reembolso', 'aura-suite'),
                    'created_by'            => get_current_user_id(),
                    'created_at'            => current_time('mysql'),
                    'updated_at'            => current_time('mysql'),
                ),
                array('%d', '%d', '%f', '%f', '%s', '%d', '%s', '%d', '%s', '%s')
            );

            if (!$ok_r) {
                wp_send_json_error(array('message' => __('No se pudo generar el registro de reembolso directo.', 'aura-suite')));
            }

            $id = (int) $wpdb->insert_id;
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$row) {
            wp_send_json_error(array('message' => __('Reembolso no encontrado.', 'aura-suite')));
        }

        if (in_array($row->status, array('paid', 'cancelled'), true)) {
            wp_send_json_error(array('message' => __('Este reembolso ya no admite pagos.', 'aura-suite')));
        }

        $remaining = max(0.0, (float) $row->owed_amount - (float) $row->paid_amount);
        if ($payment_amount - $remaining > 0.01) {
            wp_send_json_error(array('message' => __('El pago supera el saldo pendiente del reembolso.', 'aura-suite')));
        }

        // Subida de comprobante de pago
        $receipt_url = null;
        if (!empty($_FILES['receipt_file']) && !empty($_FILES['receipt_file']['name'])) {
            $upload_res = self::handle_single_receipt_upload('receipt_file', 'Reembolsos', $payment_date);
            if (is_wp_error($upload_res)) {
                wp_send_json_error(array('message' => $upload_res->get_error_message()));
            }
            $receipt_url = $upload_res;
        }

        // Crear transacción contable en Libro Mayor si está solicitado
        $created_tx_id = null;
        if ($create_tx) {
            $cat = null;
            if ($category_id > 0) {
                $cat = $wpdb->get_row($wpdb->prepare("SELECT is_capex, name FROM {$wpdb->prefix}aura_finance_categories WHERE id = %d", $category_id));
            }
            $is_capex = ($cat && $cat->is_capex == 1);
            $tx_type = $is_capex ? 'capital' : 'expense';
            $tx_desc = !empty($concept) ? $concept : sprintf(__('Pago Reembolso #%d (%s)', 'aura-suite'), $id, $cat ? $cat->name : __('Gastos', 'aura-suite'));

            $tx_notes = $notes;
            if ($receipt_url) {
                $tx_notes .= ($tx_notes ? "\n" : "") . sprintf(__('Comprobante de Pago: %s', 'aura-suite'), $receipt_url);
            }

            $tx_data = array(
                'transaction_type'   => $tx_type,
                'category_id'        => $category_id > 0 ? $category_id : null,
                'amount'             => $payment_amount,
                'transaction_date'   => $payment_date,
                'description'        => $tx_desc,
                'notes'              => $tx_notes,
                'status'             => 'approved',
                'payment_method'     => ucfirst($payment_method),
                'source_account_id'  => $paying_account_id,
                'counterparty_id'    => $row->counterparty_id > 0 ? $row->counterparty_id : null,
                'user_id'            => $row->person_user_id > 0 ? $row->person_user_id : null,
                'area_id'            => $area_id > 0 ? $area_id : null,
                'concept_link_id'    => 'expense_reimbursement',
                'business_reason'    => sprintf(__('Reembolso de gastos deuda #%d', 'aura-suite'), $id),
                'receipt_path'       => $receipt_url,
                'receipt_file'       => $receipt_url,
                'created_by'         => get_current_user_id(),
                'approved_by'        => get_current_user_id(),
                'approved_at'        => current_time('mysql'),
                'created_at'         => current_time('mysql'),
                'updated_at'         => current_time('mysql'),
            );

            $inserted = $wpdb->insert($wpdb->prefix . 'aura_finance_transactions', $tx_data);
            if ($inserted) {
                $created_tx_id = (int) $wpdb->insert_id;
            }
        }

        $movement_id = self::register_account_movement(array(
            'account_id'     => $paying_account_id,
            'transaction_id' => $created_tx_id,
            'movement_type'  => 'debit',
            'amount'         => $payment_amount,
            'reference_type' => 'reimbursement',
            'reference_id'   => $id,
            'notes'          => $notes !== '' ? $notes : sprintf(__('Pago de Reembolso #%d', 'aura-suite'), $id),
        ));

        if (!$movement_id) {
            wp_send_json_error(array('message' => __('No se pudo descontar el pago desde la cuenta seleccionada.', 'aura-suite')));
        }

        $new_paid = round((float) $row->paid_amount + $payment_amount, 2);
        $new_status = self::resolve_reimbursement_status((float) $row->owed_amount, $new_paid, (string) $row->status);

        $updated_notes = $notes !== '' ? $notes : $row->notes;
        if ($receipt_url && strpos($updated_notes, $receipt_url) === false) {
            $updated_notes .= ($updated_notes ? "\n" : "") . sprintf(__('Comprobante: %s', 'aura-suite'), $receipt_url);
        }

        $ok = $wpdb->update(
            $table,
            array(
                'paid_amount'       => $new_paid,
                'status'            => $new_status,
                'paying_account_id' => $paying_account_id,
                'notes'             => $updated_notes,
                'updated_at'        => current_time('mysql'),
            ),
            array('id' => $id),
            array('%f', '%s', '%d', '%s', '%s'),
            array('%d')
        );

        if ($ok === false) {
            wp_send_json_error(array('message' => __('Se descontó el pago, pero no se pudo actualizar el estado del reembolso.', 'aura-suite')));
        }

        wp_send_json_success(array(
            'message'        => $created_tx_id 
                ? sprintf(__('Pago de reembolso registrado y transacción #%d creada en Libro Mayor.', 'aura-suite'), $created_tx_id)
                : __('Pago de reembolso registrado correctamente.', 'aura-suite'),
            'status'         => $new_status,
            'paid_amount'    => $new_paid,
            'remaining'      => max(0, round((float) $row->owed_amount - $new_paid, 2)),
            'transaction_id' => $created_tx_id,
            'receipt_url'    => $receipt_url,
        ));
    }

    public static function ajax_delete_reimbursement() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_reimbursements';

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        if ($id <= 0) {
            wp_send_json_error(array('message' => __('ID inválido.', 'aura-suite')));
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));

        if (!$row) {
            wp_send_json_error(array('message' => __('El reembolso no existe o ya fue eliminado.', 'aura-suite')));
        }

        if ((float) $row->paid_amount > 0) {
            wp_send_json_error(array('message' => __('No se puede eliminar un reembolso que ya tiene pagos registrados para mantener la integridad contable.', 'aura-suite')));
        }

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($deleted === false) {
            wp_send_json_error(array('message' => __('Error al eliminar el reembolso de la base de datos.', 'aura-suite')));
        }

        wp_send_json_success(array('message' => __('Reembolso eliminado correctamente.', 'aura-suite')));
    }

    public static function create_reimbursement($args) {
        global $wpdb;

        $table = $wpdb->prefix . 'aura_finance_reimbursements';
        $counterparty_id = absint($args['counterparty_id'] ?? 0);
        $person_user_id = absint($args['person_user_id'] ?? 0);
        $owed_amount = isset($args['owed_amount']) ? (float) $args['owed_amount'] : 0;
        $origin_transaction_id = absint($args['origin_transaction_id'] ?? 0);
        $notes = sanitize_textarea_field($args['notes'] ?? '');
        $created_by = absint($args['created_by'] ?? get_current_user_id());

        if ($counterparty_id <= 0 && $person_user_id > 0) {
            $person = get_user_by('id', $person_user_id);
            if ($person) {
                $counterparty_id = self::ensure_third_party(
                    (string) $person->display_name,
                    array('email' => (string) $person->user_email),
                    $created_by
                );
            }
        }

        if ($counterparty_id <= 0 || $owed_amount <= 0) {
            return false;
        }

        $insert_data = array(
            'counterparty_id' => $counterparty_id,
            'origin_transaction_id' => $origin_transaction_id > 0 ? $origin_transaction_id : null,
            'owed_amount' => $owed_amount,
            'paid_amount' => 0,
            'status' => 'pending',
            'notes' => $notes,
            'created_by' => $created_by > 0 ? $created_by : get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        $insert_format = array('%d', '%d', '%f', '%f', '%s', '%s', '%d', '%s', '%s');

        // Mantener compatibilidad con integraciones legacy que aún envían person_user_id.
        if ($person_user_id > 0) {
            $insert_data['person_user_id'] = $person_user_id;
            $insert_format[] = '%d';
        }

        $ok = $wpdb->insert(
            $table,
            $insert_data,
            $insert_format
        );

        if (!$ok) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    public static function maybe_create_reimbursement_from_transaction($transaction_id, $person_user_id, $amount, $notes = '') {
        $transaction_id = absint($transaction_id);
        $person_user_id = absint($person_user_id);
        $amount = (float) $amount;

        if ($transaction_id <= 0 || $person_user_id <= 0 || $amount <= 0) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_reimbursements';
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE origin_transaction_id = %d LIMIT 1",
            $transaction_id
        ));

        if ($exists > 0) {
            return $exists;
        }

        return self::create_reimbursement(array(
            'person_user_id' => $person_user_id,
            'origin_transaction_id' => $transaction_id,
            'owed_amount' => $amount,
            'notes' => $notes,
            'created_by' => get_current_user_id(),
        ));
    }

    private static function resolve_reimbursement_status($owed_amount, $paid_amount, $current_status = 'pending') {
        if ($current_status === 'cancelled') {
            return 'cancelled';
        }

        if ($paid_amount <= 0) {
            return 'pending';
        }

        if ($paid_amount + 0.01 >= $owed_amount) {
            return 'paid';
        }

        return 'partial';
    }

    public static function get_third_parties($include_inactive = false) {
        return Aura_Third_Parties::get_third_parties($include_inactive);
    }

    private static function get_active_third_parties() {
        return Aura_Third_Parties::get_third_parties(false);
    }

    private static function get_third_party_by_id($id) {
        return Aura_Third_Parties::get_third_party_by_id($id);
    }

    public static function ensure_third_party($full_name, $extra = array(), $created_by = 0) {
        return Aura_Third_Parties::ensure_third_party($full_name, $extra, $created_by);
    }

    public static function get_app_users_for_reimbursements() {
        $user_map = array();

        // 1. Administradores de WordPress
        $admins = get_users( array(
            'role'    => 'administrator',
            'orderby' => 'display_name',
            'fields'  => array( 'ID', 'display_name', 'user_email', 'user_login' ),
        ) );
        if ( is_array( $admins ) ) {
            foreach ( $admins as $a ) {
                $user_map[ (int) $a->ID ] = array(
                    'id'           => (int) $a->ID,
                    'display_name' => $a->display_name ?: $a->user_login,
                    'user_email'   => $a->user_email,
                );
            }
        }

        // 2. Usuarios con capabilities de Aura Suite
        if ( class_exists( 'Aura_Roles_Manager' ) ) {
            $aura_users = Aura_Roles_Manager::get_aura_users();
            if ( is_array( $aura_users ) ) {
                foreach ( $aura_users as $u ) {
                    $uid = (int) $u->ID;
                    if ( ! isset( $user_map[ $uid ] ) ) {
                        $user_map[ $uid ] = array(
                            'id'           => $uid,
                            'display_name' => $u->display_name ?: $u->user_login,
                            'user_email'   => $u->user_email,
                        );
                    }
                }
            }
        }

        $users = array_values( $user_map );
        usort( $users, function( $a, $b ) {
            return strcasecmp( $a['display_name'], $b['display_name'] );
        } );

        return $users;
    }

    public static function scan_overdue_petty_cash_settlements() {
        global $wpdb;

        $table = $wpdb->prefix . 'aura_finance_petty_cash_settlements';
        $accounts = $wpdb->prefix . 'aura_finance_accounts';
        $users = $wpdb->users;
        $third_parties = $wpdb->prefix . 'aura_finance_third_parties';

        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        $has_delivery_type = is_array($columns) && in_array('delivery_type', $columns, true);
        $delivery_type_col = $has_delivery_type ? "COALESCE(s.delivery_type, 'purchase_errand') AS delivery_type," : "'purchase_errand' AS delivery_type,";

        $rows = $wpdb->get_results(
            "SELECT s.id, s.petty_cash_account_id, s.responsible_user_id, s.counterparty_id,
                    {$delivery_type_col}
                    s.delivered_amount, s.due_date,
                    s.status, s.notes, s.last_overdue_alert_at,
                    COALESCE(a.name, 'Caja Chica') AS account_name,
                    COALESCE(u.display_name, tp.full_name, 'Responsable') AS responsible_name,
                    COALESCE(u.user_email, tp.email) AS responsible_email
             FROM {$table} s
             LEFT JOIN {$accounts} a ON a.id = s.petty_cash_account_id
             LEFT JOIN {$users} u ON u.ID = s.responsible_user_id
             LEFT JOIN {$third_parties} tp ON tp.id = s.counterparty_id
             WHERE s.status IN ('open', 'submitted')
               AND s.due_date IS NOT NULL
               AND s.due_date < NOW()
               AND (s.last_overdue_alert_at IS NULL OR s.last_overdue_alert_at < DATE_SUB(NOW(), INTERVAL 20 HOUR))",
            ARRAY_A
        );

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            self::notify_petty_cash_overdue($row);

            $wpdb->update(
                $table,
                array('last_overdue_alert_at' => current_time('mysql')),
                array('id' => (int) $row['id']),
                array('%s'),
                array('%d')
            );
        }
    }

    private static function notify_petty_cash_overdue($row) {
        $is_program = (!empty($row['delivery_type']) && $row['delivery_type'] === 'program_budget');
        $subject = sprintf(
            $is_program 
                ? __('[Aura Suite] Presupuesto de Programa vencido #%d', 'aura-suite')
                : __('[Aura Suite] Rendición de compra vencida #%d', 'aura-suite'),
            (int) ($row['id'] ?? 0)
        );

        $type_label = $is_program ? __('Presupuesto de Programa', 'aura-suite') : __('Compra puntual / Diligencia', 'aura-suite');

        $message = sprintf(
            "Alerta de vencimiento contable.\n\nID: #%d\nTipo: %s\nCuenta: %s\nResponsable: %s\nEstado: %s\nVence: %s\nEntregado: %s\n\nRevisa en: %s",
            (int) ($row['id'] ?? 0),
            $type_label,
            sanitize_text_field($row['account_name'] ?? ''),
            sanitize_text_field($row['responsible_name'] ?? ''),
            sanitize_text_field($row['status'] ?? ''),
            sanitize_text_field($row['due_date'] ?? ''),
            number_format((float) ($row['delivered_amount'] ?? 0), 2),
            admin_url('admin.php?page=aura-financial-accounts')
        );

        $recipients = array(get_option('admin_email'));
        if (!empty($row['responsible_email']) && is_email($row['responsible_email'])) {
            $recipients[] = $row['responsible_email'];
        }
        $recipients = array_values(array_unique(array_filter($recipients)));

        if (!empty($recipients)) {
            wp_mail($recipients, $subject, $message);
        }

        do_action('aura_finance_petty_cash_overdue_alert_sent', $row);
    }

    private static function resolve_petty_cash_due_date($raw_due_date, $delivery_type = 'purchase_errand') {
        $raw_due_date = sanitize_text_field((string) $raw_due_date);
        if (!empty($raw_due_date)) {
            $dt = date_create($raw_due_date);
            if ($dt) {
                $dt->setTime(23, 59, 59);
                return $dt->format('Y-m-d H:i:s');
            }
        }

        $dt = new DateTime('now', wp_timezone());
        $dt->setTime(23, 59, 59);
        if ($delivery_type === 'program_budget') {
            // Presupuesto de programa: 180 días (6 meses) por defecto
            $dt->modify('+180 days');
        } else {
            // Compra puntual / diligencia: 5 días por defecto
            $dt->modify('+' . self::PETTY_CASH_DEFAULT_DUE_DAYS . ' days');
        }
        return $dt->format('Y-m-d H:i:s');
    }

    public static function handle_single_receipt_upload($input_name, $folder = 'Finanzas', $date = null) {
        if (empty($_FILES[$input_name]) || empty($_FILES[$input_name]['name'])) {
            return null;
        }

        if (!current_user_can('upload_files')) {
            return new WP_Error('upload_perm', __('No tienes permisos para subir archivos.', 'aura-suite'));
        }

        $file = $_FILES[$input_name];
        if (!empty($file['error'])) {
            return new WP_Error('upload_error', __('Error al procesar el archivo subido.', 'aura-suite'));
        }

        $filename = sanitize_file_name($file['name']);
        $mime_type = $file['type'];
        $tmp_name = $file['tmp_name'];

        if (class_exists('Aura_Drive_Manager')) {
            $drive_manager = new Aura_Drive_Manager();
            if ($drive_manager->is_ready()) {
                $drive_res = $drive_manager->upload_file($tmp_name, $filename, $mime_type, $folder, $date ?: current_time('Y-m-d'));
                if ($drive_res) {
                    if (is_array($drive_res)) {
                        return $drive_res['file_url'] ?? $drive_res['view_url'] ?? '';
                    }
                    return (string) $drive_res;
                }
            }
        }

        // Fallback a WordPress Media Library
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $_FILES['aura_single_receipt_temp'] = $file;
        $attachment_id = media_handle_upload('aura_single_receipt_temp', 0);
        unset($_FILES['aura_single_receipt_temp']);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        return wp_get_attachment_url($attachment_id);
    }

    private static function handle_petty_cash_evidence_uploads($input_name) {
        if (empty($_FILES[$input_name]) || empty($_FILES[$input_name]['name'])) {
            return array();
        }

        if (!current_user_can('upload_files')) {
            return new WP_Error('petty_upload_perm', __('No tienes permisos para subir archivos de evidencia.', 'aura-suite'));
        }

        $files = $_FILES[$input_name];
        $uploaded = array();
        
        $drive_manager = new Aura_Drive_Manager();
        $use_drive = $drive_manager->is_ready();

        if (!$use_drive) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $file_count = is_array($files['name']) ? count($files['name']) : 0;
        for ($i = 0; $i < $file_count; $i++) {
            if (empty($files['name'][$i])) {
                continue;
            }

            $single = array(
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            );
            
            $filename = sanitize_file_name($single['name']);

            if ($use_drive) {
                // Subir a Google Drive
                $drive_link = $drive_manager->upload_file($single['tmp_name'], $filename, $single['type'], 'Caja Chica');
                if ($drive_link) {
                    $uploaded[] = array(
                        'id' => 0,
                        'url' => $drive_link,
                        'name' => $filename,
                    );
                } else {
                    return new WP_Error('petty_upload_file', sprintf(__('No se pudo subir a Drive el archivo "%s".', 'aura-suite'), $filename));
                }
            } else {
                // Subir a WP Media Library
                $_FILES['aura_petty_evidence_single'] = $single;
                $attachment_id = media_handle_upload('aura_petty_evidence_single', 0);
                unset($_FILES['aura_petty_evidence_single']);

                if (is_wp_error($attachment_id)) {
                    return new WP_Error('petty_upload_file', sprintf(__('No se pudo subir el archivo "%s".', 'aura-suite'), $filename));
                }

                $uploaded[] = array(
                    'id' => (int) $attachment_id,
                    'url' => wp_get_attachment_url($attachment_id),
                    'name' => $filename,
                );
            }
        }

        return $uploaded;
    }

    public static function ajax_get_budget() {
        self::check_ajax_permissions();

        global $wpdb;
        $year = isset($_POST['year']) ? absint($_POST['year']) : (int) current_time('Y');

        $env_table = $wpdb->prefix . 'aura_finance_budget_envelopes';
        $monthly_table = $wpdb->prefix . 'aura_finance_budget_monthly';

        $envelope = $wpdb->get_row($wpdb->prepare(
            "SELECT id, fiscal_year, annual_limit, annual_spent, exceed_policy
             FROM {$env_table}
             WHERE fiscal_year = %d AND scope_type = 'global' AND scope_id = 0 AND is_active = 1
             LIMIT 1",
            $year
        ), ARRAY_A);

        $months = array_fill(1, 12, 0.0);

        $transactions_table = $wpdb->prefix . 'aura_finance_transactions';
        $categories_table = $wpdb->prefix . 'aura_finance_categories';
        $real_annual_spent = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(t.amount), 0)
             FROM {$transactions_table} t
             LEFT JOIN {$categories_table} c ON c.id = t.expense_category_id
             WHERE t.transaction_type = 'expense'
               AND t.status = 'approved'
               AND t.deleted_at IS NULL
               AND (c.is_capex IS NULL OR c.is_capex = 0)
               AND YEAR(t.transaction_date) = %d",
            $year
        ));

        // Calcular gasto real ejecutado de cada mes
        $monthly_spent_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT MONTH(t.transaction_date) AS month_num, COALESCE(SUM(t.amount), 0) AS spent
             FROM {$transactions_table} t
             LEFT JOIN {$categories_table} c ON c.id = t.expense_category_id
             WHERE t.transaction_type = 'expense'
               AND t.status = 'approved'
               AND t.deleted_at IS NULL
               AND (c.is_capex IS NULL OR c.is_capex = 0)
               AND YEAR(t.transaction_date) = %d
             GROUP BY MONTH(t.transaction_date)",
            $year
        ), ARRAY_A);

        $monthly_spent = array_fill(1, 12, 0.0);
        foreach ((array) $monthly_spent_rows as $row) {
            $m = (int) $row['month_num'];
            if ($m >= 1 && $m <= 12) {
                $monthly_spent[$m] = (float) $row['spent'];
            }
        }

        if ($envelope) {
            $envelope['annual_spent'] = $real_annual_spent;
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT month_num, monthly_limit
                 FROM {$monthly_table}
                 WHERE envelope_id = %d",
                (int) $envelope['id']
            ), ARRAY_A);

            foreach ((array) $rows as $row) {
                $m = (int) $row['month_num'];
                if ($m >= 1 && $m <= 12) {
                    $months[$m] = (float) $row['monthly_limit'];
                }
            }
        } else {
            // Return an empty envelope with just the spent amount so the UI can show the executed value
            $envelope = array(
                'annual_spent' => $real_annual_spent,
                'annual_limit' => 0,
                'exceed_policy' => 'warn'
            );
        }

        // Obtener resumen de todos los años configurados
        $configured_rows = $wpdb->get_results(
            "SELECT e.id, e.fiscal_year, e.annual_limit, e.annual_spent, e.exceed_policy, e.is_active,
                    COALESCE(SUM(m.monthly_limit), 0) AS monthly_total
             FROM {$env_table} e
             LEFT JOIN {$monthly_table} m ON m.envelope_id = e.id
             WHERE e.scope_type = 'global' AND e.scope_id = 0
             GROUP BY e.id, e.fiscal_year, e.annual_limit, e.annual_spent, e.exceed_policy, e.is_active
             ORDER BY e.fiscal_year DESC",
            ARRAY_A
        );

        wp_send_json_success(array(
            'year'             => $year,
            'envelope'         => $envelope,
            'months'           => array_values($months),
            'monthly_spent'    => array_values($monthly_spent),
            'configured_years' => $configured_rows ?: array(),
        ));
    }

    public static function ajax_save_budget() {
        self::check_ajax_permissions();

        $year = isset($_POST['year']) ? absint($_POST['year']) : 0;
        $annual_limit = isset($_POST['annual_limit']) ? (float) $_POST['annual_limit'] : 0;
        $exceed_policy = sanitize_key(wp_unslash($_POST['exceed_policy'] ?? 'warn'));
        $months_input = $_POST['monthly_limits'] ?? array();

        if ($year < 2000 || $year > 2100) {
            wp_send_json_error(array('message' => __('Año fiscal inválido.', 'aura-suite')));
        }

        if ($annual_limit < 0) {
            wp_send_json_error(array('message' => __('El presupuesto anual no puede ser negativo.', 'aura-suite')));
        }

        if (!in_array($exceed_policy, array('warn', 'block'), true)) {
            $exceed_policy = 'warn';
        }

        $monthly = array();
        for ($i = 1; $i <= 12; $i++) {
            $val = isset($months_input[$i - 1]) ? (float) $months_input[$i - 1] : 0;
            $monthly[$i] = max(0, $val);
        }

        $monthly_total = array_sum($monthly);
        if ($monthly_total > $annual_limit) {
            wp_send_json_error(array('message' => __('La suma mensual supera el presupuesto anual.', 'aura-suite')));
        }

        $result = self::upsert_budget_row($year, $annual_limit, $exceed_policy, $monthly);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        if (class_exists('Aura_Financial_Dashboard') && method_exists('Aura_Financial_Dashboard', 'bust_cache')) {
            Aura_Financial_Dashboard::bust_cache();
        }

        wp_send_json_success(array('message' => __('Presupuesto guardado correctamente.', 'aura-suite')));
    }

    public static function ajax_delete_budget() {
        self::check_ajax_permissions();

        global $wpdb;
        $year = isset($_POST['year']) ? absint($_POST['year']) : 0;

        if ($year < 2000 || $year > 2100) {
            wp_send_json_error(array('message' => __('Año fiscal inválido.', 'aura-suite')));
        }

        $env_table = $wpdb->prefix . 'aura_finance_budget_envelopes';
        $monthly_table = $wpdb->prefix . 'aura_finance_budget_monthly';

        $envelope_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$env_table} WHERE fiscal_year = %d AND scope_type = 'global' AND scope_id = 0",
            $year
        ));

        if ($envelope_id > 0) {
            $wpdb->delete($monthly_table, array('envelope_id' => $envelope_id), array('%d'));
            $wpdb->delete($env_table, array('id' => $envelope_id), array('%d'));
        }

        if (class_exists('Aura_Financial_Dashboard') && method_exists('Aura_Financial_Dashboard', 'bust_cache')) {
            Aura_Financial_Dashboard::bust_cache();
        }

        wp_send_json_success(array('message' => sprintf(__('Presupuesto del año %d eliminado correctamente.', 'aura-suite'), $year)));
    }

    public static function ajax_copy_budget() {
        self::check_ajax_permissions();

        global $wpdb;
        $from_year = isset($_POST['from_year']) ? absint($_POST['from_year']) : 0;
        $to_year   = isset($_POST['to_year']) ? absint($_POST['to_year']) : 0;

        if ($from_year < 2000 || $from_year > 2100 || $to_year < 2000 || $to_year > 2100 || $from_year === $to_year) {
            wp_send_json_error(array('message' => __('Años fiscales inválidos para clonación.', 'aura-suite')));
        }

        $env_table = $wpdb->prefix . 'aura_finance_budget_envelopes';
        $monthly_table = $wpdb->prefix . 'aura_finance_budget_monthly';

        $source_env = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$env_table} WHERE fiscal_year = %d AND scope_type = 'global' AND scope_id = 0",
            $from_year
        ), ARRAY_A);

        if (!$source_env) {
            wp_send_json_error(array('message' => sprintf(__('No existe presupuesto configurado para el año %d.', 'aura-suite'), $from_year)));
        }

        $source_months = $wpdb->get_results($wpdb->prepare(
            "SELECT month_num, monthly_limit FROM {$monthly_table} WHERE envelope_id = %d",
            (int) $source_env['id']
        ), ARRAY_A);

        $monthly = array_fill(1, 12, 0.0);
        foreach ((array) $source_months as $row) {
            $m = (int) $row['month_num'];
            if ($m >= 1 && $m <= 12) {
                $monthly[$m] = (float) $row['monthly_limit'];
            }
        }

        $result = self::upsert_budget_row($to_year, (float) $source_env['annual_limit'], $source_env['exceed_policy'], $monthly);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        if (class_exists('Aura_Financial_Dashboard') && method_exists('Aura_Financial_Dashboard', 'bust_cache')) {
            Aura_Financial_Dashboard::bust_cache();
        }

        wp_send_json_success(array('message' => sprintf(__('Presupuesto clonado exitosamente de %d a %d.', 'aura-suite'), $from_year, $to_year)));
    }

    public static function ajax_import_budget() {
        self::check_ajax_permissions();

        if (empty($_FILES['budget_file'])) {
            wp_send_json_error(array('message' => __('No se recibió archivo de importación.', 'aura-suite')));
        }

        $file = $_FILES['budget_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('csv', 'xlsx'), true)) {
            wp_send_json_error(array('message' => __('Formato no soportado. Use CSV o XLSX.', 'aura-suite')));
        }

        if (!empty($file['size']) && (int) $file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('El archivo supera el máximo de 5 MB.', 'aura-suite')));
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_send_json_error(array('message' => __('No se pudo leer el archivo temporal.', 'aura-suite')));
        }

        $rows = $ext === 'xlsx'
            ? self::parse_xlsx_rows($file['tmp_name'])
            : self::parse_csv_rows($file['tmp_name']);

        if (is_wp_error($rows)) {
            wp_send_json_error(array('message' => $rows->get_error_message()));
        }

        if (count($rows) < 2) {
            wp_send_json_error(array('message' => __('El archivo no tiene filas de datos.', 'aura-suite')));
        }

        $headers = array_shift($rows);
        $header_map = self::normalize_header_map($headers);

        $imported = 0;
        $imported_years = array();
        $errors = array();

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            if (!self::row_has_content($row)) {
                continue;
            }

            $year = (int) self::cell_by_aliases($row, $header_map, array('year', 'fiscal_year', 'ano', 'anio', 'año'));
            $annual_limit_raw = self::cell_by_aliases($row, $header_map, array('annual_limit', 'presupuesto_anual', 'tope_anual', 'anual'));
            $annual_limit = self::parse_decimal($annual_limit_raw);
            $policy_raw = (string) self::cell_by_aliases($row, $header_map, array('exceed_policy', 'policy', 'politica', 'politica_exceso'));

            if ($year < 2000 || $year > 2100) {
                $errors[] = sprintf(__('Fila %d: año fiscal inválido (%s).', 'aura-suite'), $line, esc_html($year));
                continue;
            }

            $monthly = self::extract_monthly_limits($row, $header_map);
            $monthly_total = array_sum($monthly);

            if ($annual_limit <= 0 && $monthly_total > 0) {
                $annual_limit = $monthly_total;
            } elseif ($annual_limit > 0 && $monthly_total <= 0) {
                $even = round($annual_limit / 12, 2);
                $monthly = array();
                for ($m = 1; $m <= 11; $m++) {
                    $monthly[$m] = $even;
                }
                $monthly[12] = round($annual_limit - ($even * 11), 2);
                $monthly_total = array_sum($monthly);
            }

            if ($annual_limit < 0) {
                $errors[] = sprintf(__('Fila %d: el presupuesto anual no puede ser negativo.', 'aura-suite'), $line);
                continue;
            }

            if (round($monthly_total, 2) > round($annual_limit, 2) + 0.05) {
                $errors[] = sprintf(__('Fila %d: la suma de meses ($%s) supera el tope anual ($%s).', 'aura-suite'), $line, number_format($monthly_total, 2), number_format($annual_limit, 2));
                continue;
            }

            $policy = self::normalize_policy($policy_raw);
            $upsert = self::upsert_budget_row($year, $annual_limit, $policy, $monthly);
            if (is_wp_error($upsert)) {
                $errors[] = sprintf(__('Fila %d: %s', 'aura-suite'), $line, $upsert->get_error_message());
                continue;
            }

            $imported++;
            $imported_years[] = $year;
        }

        if ($imported === 0 && !empty($errors)) {
            wp_send_json_error(array(
                'message' => __('No se pudo importar ningún presupuesto. Revisa los errores.', 'aura-suite'),
                'errors' => $errors,
            ));
        }

        wp_send_json_success(array(
            'message' => sprintf(__('¡Presupuestos importados exitosamente! Años procesados: %s.', 'aura-suite'), implode(', ', array_unique($imported_years))),
            'imported' => $imported,
            'years' => array_unique($imported_years),
            'errors' => $errors,
        ));
    }

    public static function ajax_budget_upload_preview() {
        self::check_ajax_permissions();

        if (empty($_FILES['budget_file'])) {
            wp_send_json_error(array('message' => __('No se recibió archivo de importación.', 'aura-suite')));
        }

        $file = $_FILES['budget_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('csv', 'xlsx'), true)) {
            wp_send_json_error(array('message' => __('Formato no soportado. Use CSV o XLSX.', 'aura-suite')));
        }

        if (!empty($file['size']) && (int) $file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('El archivo supera el máximo de 5 MB.', 'aura-suite')));
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_send_json_error(array('message' => __('No se pudo leer el archivo temporal.', 'aura-suite')));
        }

        $rows = $ext === 'xlsx'
            ? self::parse_xlsx_rows($file['tmp_name'])
            : self::parse_csv_rows($file['tmp_name']);

        if (is_wp_error($rows)) {
            wp_send_json_error(array('message' => $rows->get_error_message()));
        }

        if (count($rows) < 2) {
            wp_send_json_error(array('message' => __('El archivo no tiene filas de datos.', 'aura-suite')));
        }

        $headers = array_values(array_map('strval', array_shift($rows)));
        $token = wp_generate_uuid4();

        set_transient(self::BUDGET_IMPORT_TRANSIENT_PREFIX . $token, array(
            'headers' => $headers,
            'rows' => array_values($rows),
            'created_by' => get_current_user_id(),
            'created_at' => time(),
        ), HOUR_IN_SECONDS);

        wp_send_json_success(array(
            'token' => $token,
            'headers' => $headers,
            'preview' => array_slice(array_values($rows), 0, 5),
            'total_rows' => count($rows),
            'filename' => sanitize_file_name($file['name']),
            'auto_mapping' => self::auto_detect_budget_mapping($headers),
        ));
    }

    public static function ajax_budget_validate_preview() {
        self::check_ajax_permissions();

        $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
        $mapping = isset($_POST['mapping']) ? (array) $_POST['mapping'] : array();
        $payload = self::get_budget_import_payload($token);
        if (is_wp_error($payload)) {
            wp_send_json_error(array('message' => $payload->get_error_message()));
        }

        $validation = self::validate_budget_import_rows($payload['headers'], $payload['rows'], $mapping);
        wp_send_json_success($validation);
    }

    public static function ajax_budget_execute_import() {
        self::check_ajax_permissions();

        $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
        $mapping = isset($_POST['mapping']) ? (array) $_POST['mapping'] : array();
        $payload = self::get_budget_import_payload($token);
        if (is_wp_error($payload)) {
            wp_send_json_error(array('message' => $payload->get_error_message()));
        }

        $headers = $payload['headers'];
        $rows = $payload['rows'];
        $header_map = self::normalize_header_map($headers);
        $sanitized_mapping = self::sanitize_budget_mapping($mapping);

        $imported = 0;
        $errors = array();

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            if (!self::row_has_content($row)) {
                continue;
            }

            $parsed = self::parse_budget_row($row, $header_map, $line, $sanitized_mapping);
            if (is_wp_error($parsed)) {
                $errors[] = $parsed->get_error_message();
                continue;
            }

            $upsert = self::upsert_budget_row($parsed['year'], $parsed['annual_limit'], $parsed['policy'], $parsed['monthly']);
            if (is_wp_error($upsert)) {
                $errors[] = sprintf(__('Fila %d: %s', 'aura-suite'), $line, $upsert->get_error_message());
                continue;
            }

            $imported++;
        }

        delete_transient(self::BUDGET_IMPORT_TRANSIENT_PREFIX . $token);

        if ($imported === 0 && !empty($errors)) {
            wp_send_json_error(array(
                'message' => __('No se importó ningún presupuesto.', 'aura-suite'),
                'errors' => $errors,
            ));
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Importación completada. Filas importadas: %d.', 'aura-suite'), $imported),
            'imported' => $imported,
            'errors' => $errors,
        ));
    }

    public static function ajax_download_budget_template() {
        self::check_ajax_permissions();

        $csv = "\xEF\xBB\xBF";
        $csv .= "year,annual_limit,exceed_policy,jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,dec\n";
        $csv .= date('Y') . ",12000000,warn,1000000,1000000,1000000,1000000,1000000,1000000,1000000,1000000,1000000,1000000,1000000,1000000\n";

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="plantilla-presupuesto-finanzas.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $csv;
        exit;
    }

    public static function ajax_export_all_budgets() {
        self::check_ajax_permissions();
        global $wpdb;

        $env_table = $wpdb->prefix . 'aura_finance_budget_envelopes';
        $monthly_table = $wpdb->prefix . 'aura_finance_budget_monthly';

        $envelopes = $wpdb->get_results(
            "SELECT id, fiscal_year, annual_limit, exceed_policy 
             FROM {$env_table} 
             WHERE scope_type = 'global' AND scope_id = 0 
             ORDER BY fiscal_year ASC",
            ARRAY_A
        );

        $csv = "\xEF\xBB\xBF";
        $csv .= "year,annual_limit,exceed_policy,jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,dec\n";

        if (!empty($envelopes)) {
            foreach ($envelopes as $env) {
                $env_id = (int) $env['id'];
                $year = (int) $env['fiscal_year'];
                $annual_limit = number_format((float) $env['annual_limit'], 2, '.', '');
                $policy = $env['exceed_policy'] === 'block' ? 'block' : 'warn';

                $months_data = $wpdb->get_results($wpdb->prepare(
                    "SELECT month_num, monthly_limit FROM {$monthly_table} WHERE envelope_id = %d ORDER BY month_num ASC",
                    $env_id
                ), ARRAY_A);

                $m_map = array();
                for ($m = 1; $m <= 12; $m++) {
                    $m_map[$m] = '0.00';
                }
                foreach ((array) $months_data as $m_row) {
                    $num = (int) $m_row['month_num'];
                    if ($num >= 1 && $num <= 12) {
                        $m_map[$num] = number_format((float) $m_row['monthly_limit'], 2, '.', '');
                    }
                }

                $csv .= "{$year},{$annual_limit},{$policy}," . implode(',', $m_map) . "\n";
            }
        } else {
            $curr = (int) current_time('Y');
            $csv .= "{$curr},12000000.00,warn,1000000.00,1000000.00,1000000.00,1000000.00,1000000.00,1000000.00,1000000.00,1000000.00,1000000.00,1000000.00,1000000.00,1000000.00\n";
        }

        $filename = 'backup-presupuestos-aura-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $csv;
        exit;
    }

    private static function upsert_budget_row($year, $annual_limit, $exceed_policy, $monthly) {
        global $wpdb;

        $env_table = $wpdb->prefix . 'aura_finance_budget_envelopes';
        $monthly_table = $wpdb->prefix . 'aura_finance_budget_monthly';

        $envelope = $wpdb->get_row($wpdb->prepare(
            "SELECT id
             FROM {$env_table}
             WHERE fiscal_year = %d AND scope_type = 'global' AND scope_id = 0
             LIMIT 1",
            $year
        ), ARRAY_A);

        $now = current_time('mysql');

        if ($envelope) {
            $env_id = (int) $envelope['id'];
            $wpdb->update(
                $env_table,
                array(
                    'annual_limit' => $annual_limit,
                    'exceed_policy' => $exceed_policy,
                    'is_active' => 1,
                    'updated_at' => $now,
                ),
                array('id' => $env_id),
                array('%f', '%s', '%d', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $env_table,
                array(
                    'fiscal_year' => $year,
                    'scope_type' => 'global',
                    'scope_id' => 0,
                    'annual_limit' => $annual_limit,
                    'annual_spent' => 0,
                    'exceed_policy' => $exceed_policy,
                    'is_active' => 1,
                    'created_by' => get_current_user_id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array('%d', '%s', '%d', '%f', '%f', '%s', '%d', '%d', '%s', '%s')
            );
            $env_id = (int) $wpdb->insert_id;
        }

        if ($env_id <= 0) {
            return new WP_Error('budget_env', __('No se pudo guardar el encabezado de presupuesto.', 'aura-suite'));
        }

        for ($i = 1; $i <= 12; $i++) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$monthly_table} WHERE envelope_id = %d AND month_num = %d",
                $env_id,
                $i
            ));

            if ($existing) {
                $wpdb->update(
                    $monthly_table,
                    array(
                        'monthly_limit' => $monthly[$i],
                        'updated_at' => $now,
                    ),
                    array('id' => (int) $existing),
                    array('%f', '%s'),
                    array('%d')
                );
            } else {
                $wpdb->insert(
                    $monthly_table,
                    array(
                        'envelope_id' => $env_id,
                        'month_num' => $i,
                        'monthly_limit' => $monthly[$i],
                        'monthly_spent' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ),
                    array('%d', '%d', '%f', '%f', '%s', '%s')
                );
            }

            if ($wpdb->last_error) {
                return new WP_Error('budget_month', $wpdb->last_error);
            }
        }

        if (class_exists('Aura_Financial_Dashboard')) {
            Aura_Financial_Dashboard::bust_cache();
        }

        do_action('aura_finance_budget_saved', $env_id, ($envelope ? 'updated' : 'created'), array(
            'fiscal_year' => $year,
            'annual_limit' => $annual_limit,
            'exceed_policy' => $exceed_policy,
        ));

        return true;
    }

    private static function get_budget_import_payload($token) {
        if (empty($token)) {
            return new WP_Error('budget_import_token', __('Token de importación inválido.', 'aura-suite'));
        }

        $payload = get_transient(self::BUDGET_IMPORT_TRANSIENT_PREFIX . $token);
        if (!is_array($payload) || empty($payload['headers']) || !isset($payload['rows'])) {
            return new WP_Error('budget_import_expired', __('La sesión de importación expiró. Analiza el archivo nuevamente.', 'aura-suite'));
        }

        return $payload;
    }

    private static function validate_budget_import_rows($headers, $rows, $mapping = array()) {
        $header_map = self::normalize_header_map($headers);
        $sanitized_mapping = self::sanitize_budget_mapping($mapping);
        $errors = array();
        $valid = 0;

        foreach ((array) $rows as $index => $row) {
            $line = $index + 2;
            if (!self::row_has_content($row)) {
                continue;
            }

            $parsed = self::parse_budget_row($row, $header_map, $line, $sanitized_mapping);
            if (is_wp_error($parsed)) {
                $errors[] = $parsed->get_error_message();
                continue;
            }

            $valid++;
        }

        return array(
            'total' => count((array) $rows),
            'valid' => $valid,
            'invalid' => count($errors),
            'errors' => array_slice($errors, 0, 30),
        );
    }

    private static function parse_budget_row($row, $header_map, $line, $mapping = array()) {
        $year = (int) self::mapped_or_alias_value($row, $header_map, $mapping, 'year', array('year', 'fiscal_year', 'ano', 'anio', 'año'));
        $annual_limit_raw = self::mapped_or_alias_value($row, $header_map, $mapping, 'annual_limit', array('annual_limit', 'presupuesto_anual', 'tope_anual', 'anual'));
        $annual_limit = self::parse_decimal($annual_limit_raw);
        $policy_raw = (string) self::mapped_or_alias_value($row, $header_map, $mapping, 'exceed_policy', array('exceed_policy', 'policy', 'politica', 'politica_exceso'));

        if ($year < 2000 || $year > 2100) {
            return new WP_Error('budget_row', sprintf(__('Fila %d: año fiscal inválido.', 'aura-suite'), $line));
        }

        $monthly = self::extract_monthly_limits($row, $header_map, $mapping);
        $monthly_total = array_sum($monthly);

        if ($annual_limit <= 0 && $monthly_total > 0) {
            $annual_limit = $monthly_total;
        } elseif ($annual_limit > 0 && $monthly_total <= 0) {
            $even = round($annual_limit / 12, 2);
            $monthly = array();
            for ($m = 1; $m <= 11; $m++) {
                $monthly[$m] = $even;
            }
            $monthly[12] = round($annual_limit - ($even * 11), 2);
            $monthly_total = array_sum($monthly);
        }

        if ($annual_limit < 0) {
            return new WP_Error('budget_row', sprintf(__('Fila %d: el presupuesto anual no puede ser negativo.', 'aura-suite'), $line));
        }

        if (round($monthly_total, 2) > round($annual_limit, 2) + 0.05) {
            return new WP_Error('budget_row', sprintf(__('Fila %d: la suma mensual ($%s) supera el presupuesto anual ($%s).', 'aura-suite'), $line, number_format($monthly_total, 2), number_format($annual_limit, 2)));
        }

        return array(
            'year' => $year,
            'annual_limit' => $annual_limit,
            'policy' => self::normalize_policy($policy_raw),
            'monthly' => $monthly,
        );
    }

    public static function validate_expense_budget($transaction_date, $amount) {
        global $wpdb;

        $amount = (float) $amount;
        if ($amount <= 0 || empty($transaction_date)) {
            return array('allowed' => true, 'warning' => '');
        }

        $dt = DateTime::createFromFormat('d/m/Y', $transaction_date);
        if (!$dt) {
            $dt = date_create($transaction_date);
        }
        if (!$dt) {
            return array('allowed' => true, 'warning' => '');
        }

        $year = (int) $dt->format('Y');
        $month = (int) $dt->format('n');

        $env_table = $wpdb->prefix . 'aura_finance_budget_envelopes';
        $monthly_table = $wpdb->prefix . 'aura_finance_budget_monthly';
        $tx_table = $wpdb->prefix . 'aura_finance_transactions';

        $env = $wpdb->get_row($wpdb->prepare(
            "SELECT id, annual_limit, exceed_policy
             FROM {$env_table}
             WHERE fiscal_year = %d AND scope_type = 'global' AND scope_id = 0 AND is_active = 1
             LIMIT 1",
            $year
        ), ARRAY_A);

        if (!$env) {
            return array('allowed' => true, 'warning' => '');
        }

        $month_limit = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT monthly_limit FROM {$monthly_table} WHERE envelope_id = %d AND month_num = %d LIMIT 1",
            (int) $env['id'],
            $month
        ));

        $annual_spent = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount),0)
             FROM {$tx_table}
             WHERE transaction_type = 'expense'
               AND status IN ('pending','approved')
               AND deleted_at IS NULL
               AND YEAR(transaction_date) = %d",
            $year
        ));

        $monthly_spent = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount),0)
             FROM {$tx_table}
             WHERE transaction_type = 'expense'
               AND status IN ('pending','approved')
               AND deleted_at IS NULL
               AND YEAR(transaction_date) = %d
               AND MONTH(transaction_date) = %d",
            $year,
            $month
        ));

        $annual_after = $annual_spent + $amount;
        $monthly_after = $monthly_spent + $amount;
        $annual_limit = (float) $env['annual_limit'];
        $policy = $env['exceed_policy'] === 'block' ? 'block' : 'warn';

        $exceed_annual = $annual_limit > 0 && $annual_after > $annual_limit;
        $exceed_month = $month_limit > 0 && $monthly_after > $month_limit;

        if (!$exceed_annual && !$exceed_month) {
            return array('allowed' => true, 'warning' => '');
        }

        $warning_parts = array();
        if ($exceed_month) {
            $warning_parts[] = sprintf(
                __('Se excede el presupuesto mensual (%1$s / %2$s).', 'aura-suite'),
                number_format($monthly_after, 2),
                number_format($month_limit, 2)
            );
        }
        if ($exceed_annual) {
            $warning_parts[] = sprintf(
                __('Se excede el presupuesto anual (%1$s / %2$s).', 'aura-suite'),
                number_format($annual_after, 2),
                number_format($annual_limit, 2)
            );
        }

        return array(
            'allowed' => $policy !== 'block',
            'warning' => implode(' ', $warning_parts),
            'policy' => $policy,
        );
    }

    public static function get_account_by_id($account_id) {
        global $wpdb;

        $account_id = absint($account_id);
        if ($account_id <= 0) {
            return null;
        }

        $table = $wpdb->prefix . 'aura_finance_accounts';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL LIMIT 1",
                $account_id
            )
        );
    }

    public static function build_accounts_report_data($year) {
        global $wpdb;

        $accounts_table = $wpdb->prefix . 'aura_finance_accounts';
        $movements_table = $wpdb->prefix . 'aura_finance_account_movements';
        $transactions_table = $wpdb->prefix . 'aura_finance_transactions';
        $env_table = $wpdb->prefix . 'aura_finance_budget_envelopes';
        $monthly_table = $wpdb->prefix . 'aura_finance_budget_monthly';

        $accounts = $wpdb->get_results(
            "SELECT a.id, a.name, a.account_type, a.currency, a.current_balance,
                    COALESCE(SUM(CASE WHEN m.movement_type IN ('opening','credit','transfer_in') THEN m.amount ELSE 0 END),0) AS inflows,
                    COALESCE(SUM(CASE WHEN m.movement_type IN ('debit','transfer_out') THEN m.amount ELSE 0 END),0) AS outflows,
                    COUNT(m.id) AS movement_count,
                    MAX(m.created_at) AS last_movement_at
             FROM {$accounts_table} a
             LEFT JOIN {$movements_table} m ON m.account_id = a.id
             WHERE a.deleted_at IS NULL
             GROUP BY a.id, a.name, a.account_type, a.currency, a.current_balance
             ORDER BY a.currency ASC, a.account_type ASC, a.name ASC",
            ARRAY_A
        );

        $currency_summary = $wpdb->get_results(
            "SELECT currency, COUNT(*) AS account_count, COALESCE(SUM(current_balance),0) AS total_balance
             FROM {$accounts_table}
             WHERE deleted_at IS NULL
             GROUP BY currency
             ORDER BY currency ASC",
            ARRAY_A
        );

        $type_summary = $wpdb->get_results(
            "SELECT account_type, COUNT(*) AS account_count, COALESCE(SUM(current_balance),0) AS total_balance
             FROM {$accounts_table}
             WHERE deleted_at IS NULL
             GROUP BY account_type
             ORDER BY account_type ASC",
            ARRAY_A
        );

        $budget_envelope = $wpdb->get_row($wpdb->prepare(
            "SELECT id, annual_limit, exceed_policy
             FROM {$env_table}
             WHERE fiscal_year = %d AND scope_type = 'global' AND scope_id = 0 AND is_active = 1
             LIMIT 1",
            $year
        ), ARRAY_A);

        $categories_table = $wpdb->prefix . 'aura_finance_categories';

        $annual_spent = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(t.amount), 0)
             FROM {$transactions_table} t
             LEFT JOIN {$categories_table} c ON c.id = t.expense_category_id
             WHERE t.transaction_type = 'expense'
               AND t.status = 'approved'
               AND t.deleted_at IS NULL
               AND (c.is_capex IS NULL OR c.is_capex = 0)
               AND YEAR(t.transaction_date) = %d",
            $year
        ));

        $monthly_limits = array_fill(1, 12, 0.0);
        if (!empty($budget_envelope['id'])) {
            $monthly_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT month_num, monthly_limit
                 FROM {$monthly_table}
                 WHERE envelope_id = %d",
                (int) $budget_envelope['id']
            ), ARRAY_A);
            foreach ((array) $monthly_rows as $row) {
                $month_num = (int) $row['month_num'];
                if ($month_num >= 1 && $month_num <= 12) {
                    $monthly_limits[$month_num] = (float) $row['monthly_limit'];
                }
            }
        }

        $monthly_spent_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT MONTH(t.transaction_date) AS month_num, COALESCE(SUM(t.amount),0) AS spent
             FROM {$transactions_table} t
             LEFT JOIN {$categories_table} c ON c.id = t.expense_category_id
             WHERE t.transaction_type = 'expense'
               AND t.status = 'approved'
               AND t.deleted_at IS NULL
               AND (c.is_capex IS NULL OR c.is_capex = 0)
               AND YEAR(t.transaction_date) = %d
             GROUP BY MONTH(t.transaction_date)",
            $year
        ), ARRAY_A);

        $monthly_spent = array_fill(1, 12, 0.0);
        foreach ((array) $monthly_spent_rows as $row) {
            $month_num = (int) $row['month_num'];
            if ($month_num >= 1 && $month_num <= 12) {
                $monthly_spent[$month_num] = (float) $row['spent'];
            }
        }

        $budget_months = array();
        for ($i = 1; $i <= 12; $i++) {
            $budget_months[] = array(
                'month_num' => $i,
                'limit' => (float) $monthly_limits[$i],
                'spent' => (float) $monthly_spent[$i],
                'remaining' => round((float) $monthly_limits[$i] - (float) $monthly_spent[$i], 2),
            );
        }

        $missing_account_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$transactions_table}
             WHERE deleted_at IS NULL
               AND status = 'approved'
               AND YEAR(transaction_date) = %d
               AND (
                    (transaction_type = 'income' AND destination_account_id IS NULL)
                    OR
                    (transaction_type = 'expense' AND source_account_id IS NULL AND related_user_id IS NULL)
               )",
            $year
        ));

        $approved_with_account = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$transactions_table}
             WHERE deleted_at IS NULL
               AND status = 'approved'
               AND YEAR(transaction_date) = %d
               AND (
                    (transaction_type = 'income' AND destination_account_id IS NOT NULL)
                    OR
                    (transaction_type = 'expense' AND source_account_id IS NOT NULL)
               )",
            $year
        ));

        $approved_without_movement = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$transactions_table} t
             LEFT JOIN {$movements_table} m
                ON m.transaction_id = t.id AND m.reference_type = 'transaction'
             WHERE t.deleted_at IS NULL
               AND t.status = 'approved'
               AND YEAR(t.transaction_date) = %d
               AND (
                    (t.transaction_type = 'income' AND t.destination_account_id IS NOT NULL)
                    OR
                    (t.transaction_type = 'expense' AND t.source_account_id IS NOT NULL)
               )
               AND m.id IS NULL",
            $year
        ));

        $orphan_movements = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$movements_table} m
             LEFT JOIN {$transactions_table} t ON t.id = m.transaction_id
             WHERE m.reference_type = 'transaction'
               AND m.transaction_id IS NOT NULL
               AND t.id IS NULL"
        );

        $negative_accounts = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$accounts_table}
             WHERE deleted_at IS NULL AND current_balance < 0"
        );

        $cash_flow_totals = array(
            'inflows' => array_sum(array_map('floatval', wp_list_pluck($accounts, 'inflows'))),
            'outflows' => array_sum(array_map('floatval', wp_list_pluck($accounts, 'outflows'))),
        );

        return array(
            'generated_at' => current_time('mysql'),
            'year' => $year,
            'accounts' => $accounts,
            'currency_summary' => $currency_summary,
            'type_summary' => $type_summary,
            'cash_flow_totals' => $cash_flow_totals,
            'budget' => array(
                'annual_limit' => !empty($budget_envelope['annual_limit']) ? (float) $budget_envelope['annual_limit'] : 0,
                'annual_spent' => $annual_spent,
                'annual_remaining' => (!empty($budget_envelope['annual_limit']) ? round((float) $budget_envelope['annual_limit'] - $annual_spent, 2) : 0),
                'policy' => !empty($budget_envelope['exceed_policy']) ? $budget_envelope['exceed_policy'] : 'warn',
                'months' => $budget_months,
            ),
            'audit' => array(
                'approved_with_account' => $approved_with_account,
                'missing_account_count' => $missing_account_count,
                'approved_without_movement' => $approved_without_movement,
                'orphan_movements' => $orphan_movements,
                'negative_accounts' => $negative_accounts,
            ),
        );
    }

    public static function get_usd_cash_account() {
        global $wpdb;

        $table = $wpdb->prefix . 'aura_finance_accounts';
        return $wpdb->get_row(
            "SELECT * FROM {$table}
             WHERE deleted_at IS NULL AND account_type = 'usd_cash'
             ORDER BY id ASC
             LIMIT 1"
        );
    }

    public static function register_account_movement($args) {
        global $wpdb;

        $account_id = absint($args['account_id'] ?? 0);
        $amount = isset($args['amount']) ? (float) $args['amount'] : 0.0;
        $movement_type = sanitize_key($args['movement_type'] ?? 'adjustment');

        if ($account_id <= 0 || $amount <= 0) {
            return false;
        }

        $account = self::get_account_by_id($account_id);
        if (!$account) {
            return false;
        }

        $accounts_table = $wpdb->prefix . 'aura_finance_accounts';
        $movements_table = $wpdb->prefix . 'aura_finance_account_movements';

        $balance_delta = 0.0;
        if (in_array($movement_type, array('credit', 'transfer_in', 'opening'), true)) {
            $balance_delta = $amount;
        } elseif (in_array($movement_type, array('debit', 'transfer_out'), true)) {
            $balance_delta = -$amount;
        }

        $ok = $wpdb->insert(
            $movements_table,
            array(
                'account_id' => $account_id,
                'transaction_id' => !empty($args['transaction_id']) ? absint($args['transaction_id']) : null,
                'movement_type' => $movement_type,
                'amount' => $amount,
                'currency' => sanitize_text_field($args['currency'] ?? $account->currency),
                'exchange_rate' => isset($args['exchange_rate']) ? (float) $args['exchange_rate'] : null,
                'reference_type' => sanitize_text_field($args['reference_type'] ?? 'transaction'),
                'reference_id' => !empty($args['reference_id']) ? absint($args['reference_id']) : null,
                'notes' => sanitize_textarea_field($args['notes'] ?? ''),
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%f', '%s', '%f', '%s', '%d', '%s', '%d', '%s')
        );

        if (!$ok) {
            return false;
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$accounts_table}
                 SET current_balance = current_balance + %f,
                     updated_at = %s
                 WHERE id = %d",
                $balance_delta,
                current_time('mysql'),
                $account_id
            )
        );

        return (int) $wpdb->insert_id;
    }

    public static function migrate_petty_cash_counterparty_model() {
        global $wpdb;

        $settlements = $wpdb->prefix . 'aura_finance_petty_cash_settlements';

        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$settlements}", 0);
        if (!is_array($columns)) {
            return;
        }

        if (!in_array('counterparty_id', $columns, true)) {
            $wpdb->query("ALTER TABLE {$settlements} MODIFY COLUMN responsible_user_id BIGINT UNSIGNED NULL");
            $wpdb->query("ALTER TABLE {$settlements} ADD COLUMN counterparty_id BIGINT UNSIGNED NULL AFTER responsible_user_id");
            $wpdb->query("ALTER TABLE {$settlements} ADD KEY idx_counterparty (counterparty_id)");
        }

        if (!in_array('category_id', $columns, true)) {
            $wpdb->query("ALTER TABLE {$settlements} ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER counterparty_id");
            $wpdb->query("ALTER TABLE {$settlements} ADD KEY idx_category (category_id)");
        }
    }

    public static function migrate_usd_ledger_to_accounts() {
        global $wpdb;

        $legacy_table = $wpdb->prefix . 'aura_finance_usd_ledger';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_table));
        if ($exists !== $legacy_table) {
            return get_option(self::USD_LEDGER_MIGRATION_OPTION, array());
        }

        $usd_account = self::ensure_usd_cash_account_for_migration();
        if (!$usd_account) {
            return false;
        }

        $movements_table = $wpdb->prefix . 'aura_finance_account_movements';
        $rows = $wpdb->get_results(
            "SELECT id, entry_type, usd_amount, exchange_rate, mxn_amount, transaction_id, notes, created_by, created_at
             FROM {$legacy_table}
             ORDER BY created_at ASC, id ASC"
        );

        $migrated = 0;
        foreach ((array) $rows as $row) {
            if (self::usd_legacy_entry_already_migrated((int) $usd_account->id, (int) $row->id)) {
                continue;
            }

            $movement_type = 'adjustment';
            if ($row->entry_type === 'opening') {
                $movement_type = 'opening';
            } elseif ($row->entry_type === 'deposit') {
                $movement_type = 'credit';
            } elseif ($row->entry_type === 'conversion') {
                $movement_type = 'debit';
            }

            $notes = sprintf(
                '[USD-LEGACY:%1$d][%2$s] %3$s',
                (int) $row->id,
                strtoupper((string) $row->entry_type),
                sanitize_textarea_field((string) $row->notes)
            );

            $ok = $wpdb->insert(
                $movements_table,
                array(
                    'account_id' => (int) $usd_account->id,
                    'transaction_id' => !empty($row->transaction_id) ? (int) $row->transaction_id : null,
                    'movement_type' => $movement_type,
                    'amount' => (float) $row->usd_amount,
                    'currency' => 'USD',
                    'exchange_rate' => $row->exchange_rate !== null ? (float) $row->exchange_rate : null,
                    'reference_type' => 'manual',
                    'reference_id' => (int) $row->id,
                    'notes' => $notes,
                    'created_by' => !empty($row->created_by) ? (int) $row->created_by : 1,
                    'created_at' => !empty($row->created_at) ? $row->created_at : current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%f', '%s', '%f', '%s', '%d', '%s', '%d', '%s')
            );

            if ($ok) {
                $migrated++;
            }
        }

        // Solo recalcular cuando realmente se migraron filas nuevas.
        // Si no hay nuevas filas del ledger legacy, no debemos pisar ajustes manuales del saldo.
        if ($migrated > 0) {
            self::recalculate_account_balance((int) $usd_account->id);
        }

        $already_migrated_total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$movements_table}
             WHERE account_id = %d AND reference_type = 'manual'",
            (int) $usd_account->id
        ));

        $summary = array(
            'account_id' => (int) $usd_account->id,
            'migrated_rows_last_run' => (int) $migrated,
            'migrated_rows_total' => $already_migrated_total,
            'legacy_rows' => count((array) $rows),
            'last_run_at' => current_time('mysql'),
        );

        update_option(self::USD_LEDGER_MIGRATION_OPTION, $summary, false);

        return $summary;
    }

    private static function ensure_usd_cash_account_for_migration() {
        global $wpdb;

        $existing = self::get_usd_cash_account();
        if ($existing) {
            return $existing;
        }

        $table = $wpdb->prefix . 'aura_finance_accounts';
        $wpdb->insert(
            $table,
            array(
                'name' => __('Caja USD', 'aura-suite'),
                'account_type' => 'usd_cash',
                'currency' => 'USD',
                'institution' => __('Migrada desde ledger USD', 'aura-suite'),
                'initial_balance' => 0,
                'current_balance' => 0,
                'is_active' => 1,
                'meta_json' => wp_json_encode(array('legacy_source' => 'aura_finance_usd_ledger')),
                'created_by' => get_current_user_id() ?: 1,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%d', '%s')
        );

        if (!$wpdb->insert_id) {
            return null;
        }

        return self::get_account_by_id((int) $wpdb->insert_id);
    }

    private static function usd_legacy_entry_already_migrated($account_id, $legacy_entry_id) {
        global $wpdb;

        $movements_table = $wpdb->prefix . 'aura_finance_account_movements';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$movements_table}
             WHERE account_id = %d AND reference_type = 'manual' AND reference_id = %d
             LIMIT 1",
            $account_id,
            $legacy_entry_id
        ));

        return !empty($exists);
    }

    public static function recalculate_account_balance($account_id) {
        global $wpdb;

        $account = self::get_account_by_id($account_id);
        if (!$account) {
            return false;
        }

        $movements_table = $wpdb->prefix . 'aura_finance_account_movements';
        $credits = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$movements_table}
             WHERE account_id = %d AND movement_type IN ('opening','credit','transfer_in')",
            $account_id
        ));
        $debits = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$movements_table}
             WHERE account_id = %d AND movement_type IN ('debit','transfer_out')",
            $account_id
        ));
        $adjustments = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$movements_table}
             WHERE account_id = %d AND movement_type = 'adjustment'",
            $account_id
        ));

        $new_balance = round($credits - $debits + $adjustments, 2);

        $accounts_table = $wpdb->prefix . 'aura_finance_accounts';
        return $wpdb->update(
            $accounts_table,
            array(
                'current_balance' => $new_balance,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $account_id),
            array('%f', '%s'),
            array('%d')
        );
    }

    public static function sync_transaction_accounts($transaction_id, $transaction_type, $amount, $source_account_id = 0, $destination_account_id = 0, $options = array()) {
        $transaction_id = absint($transaction_id);
        $amount = (float) $amount;

        if ($transaction_id <= 0 || $amount <= 0) {
            return false;
        }

        if ($transaction_type === 'income' && $destination_account_id > 0) {
            return self::register_account_movement(array(
                'account_id' => $destination_account_id,
                'transaction_id' => $transaction_id,
                'movement_type' => 'credit',
                'amount' => $amount,
                'currency' => $options['currency'] ?? 'COP',
                'reference_type' => 'transaction',
                'reference_id' => $transaction_id,
                'notes' => $options['notes'] ?? '',
            ));
        }

        if ($transaction_type === 'expense' && $source_account_id > 0) {
            return self::register_account_movement(array(
                'account_id' => $source_account_id,
                'transaction_id' => $transaction_id,
                'movement_type' => 'debit',
                'amount' => $amount,
                'currency' => $options['currency'] ?? 'COP',
                'reference_type' => 'transaction',
                'reference_id' => $transaction_id,
                'notes' => $options['notes'] ?? '',
            ));
        }

        return true;
    }

    private static function check_ajax_permissions($required_cap = null) {
        check_ajax_referer('aura_financial_accounts_nonce', 'nonce');

        if (current_user_can('manage_options')) {
            return true;
        }

        if ($required_cap && current_user_can($required_cap)) {
            return true;
        }

        if (!(current_user_can('aura_finance_view_all') || current_user_can('manage_options'))) {
            wp_send_json_error(array('message' => __('No autorizado.', 'aura-suite')), 403);
        }
    }

    private static function parse_csv_rows($filepath) {
        $rows = array();
        $content = file_get_contents($filepath);
        if ($content === false) {
            return new WP_Error('budget_csv', __('No se pudo leer el CSV.', 'aura-suite'));
        }

        // Remover UTF-8 BOM si existe
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        if (function_exists('mb_detect_encoding')) {
            $enc = mb_detect_encoding($content, array('UTF-8', 'ISO-8859-1', 'Windows-1252'), true);
            if ($enc && $enc !== 'UTF-8') {
                $content = mb_convert_encoding($content, 'UTF-8', $enc);
            }
        }
        file_put_contents($filepath, $content);

        $sample = substr($content, 0, 2000);
        $counts = array(',' => substr_count($sample, ','), ';' => substr_count($sample, ';'), "\t" => substr_count($sample, "\t"));
        arsort($counts);
        $delimiter = key($counts);

        $handle = fopen($filepath, 'r');
        if (!$handle) {
            return new WP_Error('budget_csv_open', __('No se pudo abrir el CSV.', 'aura-suite'));
        }

        while (($row = fgetcsv($handle, 4096, $delimiter)) !== false) {
            if (self::row_has_content($row)) {
                $rows[] = array_map(function ($v) {
                    return is_string($v) ? trim($v) : (string) $v;
                }, $row);
            }
        }
        fclose($handle);

        if (count($rows) > self::BUDGET_IMPORT_MAX_ROWS + 1) {
            return new WP_Error('budget_csv_rows', sprintf(__('El archivo supera %d filas.', 'aura-suite'), self::BUDGET_IMPORT_MAX_ROWS));
        }

        return $rows;
    }

    private static function parse_xlsx_rows($filepath) {
        $autoload = AURA_PLUGIN_DIR . 'vendor/autoload.php';
        if (!file_exists($autoload)) {
            return new WP_Error('budget_xlsx_vendor', __('PhpSpreadsheet no disponible para leer XLSX.', 'aura-suite'));
        }

        require_once $autoload;

        try {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filepath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, false);

            $rows = array_values(array_filter($data, function ($row) {
                return self::row_has_content($row);
            }));

            if (count($rows) > self::BUDGET_IMPORT_MAX_ROWS + 1) {
                return new WP_Error('budget_xlsx_rows', sprintf(__('El archivo supera %d filas.', 'aura-suite'), self::BUDGET_IMPORT_MAX_ROWS));
            }

            return array_map(function ($row) {
                return array_map(function ($v) {
                    return $v !== null ? trim((string) $v) : '';
                }, $row);
            }, $rows);
        } catch (\Exception $e) {
            return new WP_Error('budget_xlsx_parse', $e->getMessage());
        }
    }

    private static function normalize_header_map($headers) {
        $map = array();
        foreach ((array) $headers as $idx => $head) {
            $key = self::normalize_key($head);
            if ($key !== '') {
                $map[$key] = (int) $idx;
            }
        }
        return $map;
    }

    private static function auto_detect_budget_mapping($headers) {
        $header_map = self::normalize_header_map($headers);
        $fields = array(
            'year' => array('year', 'fiscal_year', 'ano', 'anio', 'año'),
            'annual_limit' => array('annual_limit', 'presupuesto_anual', 'tope_anual', 'anual'),
            'exceed_policy' => array('exceed_policy', 'policy', 'politica', 'politica_exceso'),
            'jan' => array('jan', 'enero', 'ene', 'month_1', 'mes_1', 'm1'),
            'feb' => array('feb', 'febrero', 'month_2', 'mes_2', 'm2'),
            'mar' => array('mar', 'marzo', 'month_3', 'mes_3', 'm3'),
            'apr' => array('apr', 'abril', 'abr', 'month_4', 'mes_4', 'm4'),
            'may' => array('may', 'mayo', 'month_5', 'mes_5', 'm5'),
            'jun' => array('jun', 'junio', 'month_6', 'mes_6', 'm6'),
            'jul' => array('jul', 'julio', 'month_7', 'mes_7', 'm7'),
            'aug' => array('aug', 'agosto', 'ago', 'month_8', 'mes_8', 'm8'),
            'sep' => array('sep', 'septiembre', 'setiembre', 'month_9', 'mes_9', 'm9'),
            'oct' => array('oct', 'octubre', 'month_10', 'mes_10', 'm10'),
            'nov' => array('nov', 'noviembre', 'month_11', 'mes_11', 'm11'),
            'dec' => array('dec', 'diciembre', 'dic', 'month_12', 'mes_12', 'm12'),
        );

        $mapping = array();
        foreach ($fields as $field => $aliases) {
            $mapping[$field] = '';
            foreach ($aliases as $alias) {
                $key = self::normalize_key($alias);
                if (isset($header_map[$key])) {
                    $mapping[$field] = (string) $header_map[$key];
                    break;
                }
            }
        }

        return $mapping;
    }

    private static function sanitize_budget_mapping($mapping) {
        $allowed = array('year', 'annual_limit', 'exceed_policy', 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec');
        $sanitized = array();
        foreach ($allowed as $field) {
            if (!isset($mapping[$field]) || $mapping[$field] === '') {
                $sanitized[$field] = '';
                continue;
            }
            $value = is_numeric($mapping[$field]) ? (int) $mapping[$field] : '';
            $sanitized[$field] = $value >= 0 ? $value : '';
        }
        return $sanitized;
    }

    private static function mapped_or_alias_value($row, $header_map, $mapping, $field, $aliases) {
        if (isset($mapping[$field]) && $mapping[$field] !== '' && is_int($mapping[$field])) {
            $idx = $mapping[$field];
            return isset($row[$idx]) ? trim((string) $row[$idx]) : '';
        }
        return self::cell_by_aliases($row, $header_map, $aliases);
    }

    private static function cell_by_aliases($row, $header_map, $aliases) {
        foreach ($aliases as $alias) {
            $key = self::normalize_key($alias);
            if (isset($header_map[$key])) {
                $idx = (int) $header_map[$key];
                return isset($row[$idx]) ? trim((string) $row[$idx]) : '';
            }
        }
        return '';
    }

    private static function extract_monthly_limits($row, $header_map, $mapping = array()) {
        $monthly = array();
        $aliases = array(
            1 => array('jan', 'enero', 'ene', 'month_1', 'mes_1', 'm1'),
            2 => array('feb', 'febrero', 'month_2', 'mes_2', 'm2'),
            3 => array('mar', 'marzo', 'month_3', 'mes_3', 'm3'),
            4 => array('apr', 'abril', 'abr', 'month_4', 'mes_4', 'm4'),
            5 => array('may', 'mayo', 'month_5', 'mes_5', 'm5'),
            6 => array('jun', 'junio', 'month_6', 'mes_6', 'm6'),
            7 => array('jul', 'julio', 'month_7', 'mes_7', 'm7'),
            8 => array('aug', 'agosto', 'ago', 'month_8', 'mes_8', 'm8'),
            9 => array('sep', 'septiembre', 'setiembre', 'month_9', 'mes_9', 'm9'),
            10 => array('oct', 'octubre', 'month_10', 'mes_10', 'm10'),
            11 => array('nov', 'noviembre', 'month_11', 'mes_11', 'm11'),
            12 => array('dec', 'diciembre', 'dic', 'month_12', 'mes_12', 'm12'),
        );

        $fields = array(
            1 => 'jan',
            2 => 'feb',
            3 => 'mar',
            4 => 'apr',
            5 => 'may',
            6 => 'jun',
            7 => 'jul',
            8 => 'aug',
            9 => 'sep',
            10 => 'oct',
            11 => 'nov',
            12 => 'dec',
        );

        for ($i = 1; $i <= 12; $i++) {
            $raw = self::mapped_or_alias_value($row, $header_map, $mapping, $fields[$i], $aliases[$i]);
            $monthly[$i] = self::parse_decimal($raw);
        }

        return $monthly;
    }

    private static function parse_decimal($value) {
        if ($value === '' || $value === null) {
            return 0;
        }

        $value = trim((string) $value);
        $value = preg_replace('/[^\d.,\-]/', '', $value);
        if ($value === '' || $value === '-') {
            return 0;
        }

        if (preg_match('/^[\d]+(\.\d{3})+(,\d{1,2})?$/', $value)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (preg_match('/^[\d]+(,\d{3})+(\.?\d{1,2})?$/', $value)) {
            $value = str_replace(',', '', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        $num = is_numeric($value) ? (float) $value : 0;
        return max(0, $num);
    }

    private static function normalize_policy($value) {
        $v = strtolower(trim((string) $value));
        if (in_array($v, array('block', 'bloquear', 'stop'), true)) {
            return 'block';
        }
        return 'warn';
    }

    private static function normalize_key($value) {
        $value = strtolower(trim((string) $value));
        $replace = array('á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n');
        $value = strtr($value, $replace);
        $value = preg_replace('/\s+/', '_', $value);
        $value = preg_replace('/[^a-z0-9_]/', '', $value);
        return $value;
    }

    private static function row_has_content($row) {
        foreach ((array) $row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return true;
            }
        }
        return false;
    }
}
