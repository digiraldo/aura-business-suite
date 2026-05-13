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
    const DB_VERSION = '1.5.0';
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
        add_action('wp_ajax_aura_finance_petty_cash_submit', array(__CLASS__, 'ajax_submit_petty_cash_settlement'));
        add_action('wp_ajax_aura_finance_petty_cash_status', array(__CLASS__, 'ajax_update_petty_cash_status'));
        add_action('wp_ajax_aura_finance_reimbursements_list', array(__CLASS__, 'ajax_list_reimbursements'));
        add_action('wp_ajax_aura_finance_reimbursements_create', array(__CLASS__, 'ajax_create_reimbursement'));
        add_action('wp_ajax_aura_finance_reimbursements_pay', array(__CLASS__, 'ajax_pay_reimbursement'));
        add_action('wp_ajax_aura_finance_third_parties_list', array(__CLASS__, 'ajax_list_third_parties'));
        add_action('wp_ajax_aura_finance_third_parties_create', array(__CLASS__, 'ajax_create_third_party'));
        add_action('wp_ajax_aura_finance_third_parties_update', array(__CLASS__, 'ajax_update_third_party'));
        add_action('wp_ajax_aura_finance_third_parties_toggle', array(__CLASS__, 'ajax_toggle_third_party'));
        add_action('wp_ajax_aura_finance_third_parties_convert_user', array(__CLASS__, 'ajax_convert_third_party_to_user'));
        add_action('wp_ajax_aura_finance_accounts_report', array(__CLASS__, 'ajax_get_accounts_report'));
        add_action('wp_ajax_aura_finance_budget_get', array(__CLASS__, 'ajax_get_budget'));
        add_action('wp_ajax_aura_finance_budget_save', array(__CLASS__, 'ajax_save_budget'));
        add_action('wp_ajax_aura_finance_budget_import', array(__CLASS__, 'ajax_import_budget'));
        add_action('wp_ajax_aura_finance_budget_template', array(__CLASS__, 'ajax_download_budget_template'));
        add_action('wp_ajax_aura_finance_budget_upload_preview', array(__CLASS__, 'ajax_budget_upload_preview'));
        add_action('wp_ajax_aura_finance_budget_validate_preview', array(__CLASS__, 'ajax_budget_validate_preview'));
        add_action('wp_ajax_aura_finance_budget_execute_import', array(__CLASS__, 'ajax_budget_execute_import'));
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

        self::migrate_usd_ledger_to_accounts();

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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql_accounts = "CREATE TABLE {$accounts} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            account_type ENUM('bank_account','petty_cash','contributions_fund','usd_cash','custom') NOT NULL DEFAULT 'bank_account',
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
            responsible_user_id BIGINT UNSIGNED NOT NULL,
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
            document_id VARCHAR(80) NULL,
            phone VARCHAR(50) NULL,
            email VARCHAR(100) NULL,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            wp_user_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_name (full_name),
            KEY idx_active (is_active),
            KEY idx_wp_user (wp_user_id)
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

        dbDelta($sql_accounts);
        dbDelta($sql_movements);
        dbDelta($sql_settlements);
        dbDelta($sql_reimbursements);
        dbDelta($sql_third_parties);
        dbDelta($sql_budget_envelopes);
        dbDelta($sql_budget_monthly);
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
    }

    private static function migrate_settlements_phase3_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_petty_cash_settlements';

        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!is_array($columns)) {
            return;
        }

        if (!in_array('due_date', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN due_date DATETIME NULL AFTER status");
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table}
                 SET due_date = DATE_ADD(created_at, INTERVAL %d DAY)
                 WHERE due_date IS NULL",
                self::PETTY_CASH_DEFAULT_DUE_DAYS
            ));
        }

        if (!in_array('last_overdue_alert_at', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN last_overdue_alert_at DATETIME NULL AFTER due_date");
        }
    }

    private static function ensure_overdue_cron_scheduled() {
        if (!wp_next_scheduled(self::PETTY_CASH_OVERDUE_CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::PETTY_CASH_OVERDUE_CRON_HOOK);
        }
    }

    public static function add_menu() {
        if (!(current_user_can('aura_finance_view_all') || current_user_can('manage_options'))) {
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
        if ($hook !== 'finanzas_page_aura-financial-accounts') {
            return;
        }

        wp_enqueue_style(
            'datatables-css',
            'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css',
            array(),
            '2.2.2'
        );

        wp_enqueue_style(
            'datatables-responsive-css',
            'https://cdn.datatables.net/responsive/3.0.4/css/responsive.dataTables.min.css',
            array('datatables-css'),
            '3.0.4'
        );

        wp_enqueue_style(
            'aura-financial-accounts',
            AURA_PLUGIN_URL . 'assets/css/financial-accounts.css',
            array('datatables-css', 'datatables-responsive-css'),
            AURA_VERSION
        );

        wp_enqueue_script(
            'datatables-js',
            'https://cdn.datatables.net/2.2.2/js/dataTables.min.js',
            array('jquery'),
            '2.2.2',
            true
        );

        wp_enqueue_script(
            'datatables-responsive-js',
            'https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js',
            array('datatables-js'),
            '3.0.4',
            true
        );

        wp_enqueue_script(
            'aura-financial-accounts',
            AURA_PLUGIN_URL . 'assets/js/financial-accounts.js',
            array('jquery', 'datatables-js', 'datatables-responsive-js'),
            AURA_VERSION,
            true
        );

        wp_enqueue_media();

        wp_localize_script('aura-financial-accounts', 'auraFinancialAccounts', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aura_financial_accounts_nonce'),
            'canManage' => (current_user_can('aura_finance_view_all') || current_user_can('manage_options')),
            'defaultDueDays' => self::PETTY_CASH_DEFAULT_DUE_DAYS,
            'initialFilters' => array(
                'type' => sanitize_key(wp_unslash($_GET['account_type'] ?? '')),
                'currency' => strtoupper(sanitize_text_field(wp_unslash($_GET['currency'] ?? ''))),
            ),
            'i18n' => array(
                'saving' => __('Guardando...', 'aura-suite'),
                'saved' => __('Cuenta guardada correctamente.', 'aura-suite'),
                'deleted' => __('Cuenta eliminada correctamente.', 'aura-suite'),
                'deleteConfirm' => __('¿Eliminar esta cuenta? Esta acción no elimina transacciones históricas.', 'aura-suite'),
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

        $valid_types = array('bank_account', 'petty_cash', 'contributions_fund', 'usd_cash', 'custom');
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

    public static function ajax_list_petty_cash_settlements() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_petty_cash_settlements';
        $accounts = $wpdb->prefix . 'aura_finance_accounts';
        $users = $wpdb->users;

        $rows = $wpdb->get_results(
            "SELECT s.id, s.petty_cash_account_id, s.responsible_user_id, s.delivered_amount, s.spent_amount, s.returned_amount,
                    s.status, s.due_date, s.evidence_json, s.notes, s.created_at, s.updated_at,
                    CASE
                        WHEN s.status IN ('open', 'submitted') AND s.due_date IS NOT NULL AND s.due_date < NOW() THEN 1
                        ELSE 0
                    END AS is_overdue,
                    a.name AS account_name,
                    u.display_name AS responsible_name
             FROM {$table} s
             INNER JOIN {$accounts} a ON a.id = s.petty_cash_account_id
             LEFT JOIN {$users} u ON u.ID = s.responsible_user_id
             WHERE a.deleted_at IS NULL
             ORDER BY s.created_at DESC, s.id DESC
             LIMIT 200",
            ARRAY_A
        );

        $petty_cash_accounts = $wpdb->get_results(
            "SELECT id, name, currency, current_balance
             FROM {$accounts}
             WHERE deleted_at IS NULL AND is_active = 1 AND account_type = 'petty_cash'
             ORDER BY name ASC",
            ARRAY_A
        );

        wp_send_json_success(array(
            'settlements' => $rows,
            'petty_cash_accounts' => $petty_cash_accounts,
            'can_approve' => (current_user_can('aura_finance_petty_cash_approve') || current_user_can('aura_finance_approve') || current_user_can('manage_options')),
        ));
    }

    public static function ajax_create_petty_cash_settlement() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_petty_cash_settlements';

        $petty_cash_account_id = absint($_POST['petty_cash_account_id'] ?? 0);
        $responsible_user_id = absint($_POST['responsible_user_id'] ?? 0);
        $delivered_amount = isset($_POST['delivered_amount']) ? (float) $_POST['delivered_amount'] : 0;
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

        if ($petty_cash_account_id <= 0) {
            wp_send_json_error(array('message' => __('Debes seleccionar una cuenta de caja chica.', 'aura-suite')));
        }

        $account = self::get_account_by_id($petty_cash_account_id);
        if (!$account || $account->account_type !== 'petty_cash') {
            wp_send_json_error(array('message' => __('La cuenta seleccionada no corresponde a caja chica.', 'aura-suite')));
        }

        if ($responsible_user_id <= 0 || !get_user_by('id', $responsible_user_id)) {
            wp_send_json_error(array('message' => __('Debes indicar un responsable válido.', 'aura-suite')));
        }

        if ($delivered_amount <= 0) {
            wp_send_json_error(array('message' => __('El monto entregado debe ser mayor a cero.', 'aura-suite')));
        }

        $ok = $wpdb->insert(
            $table,
            array(
                'petty_cash_account_id' => $petty_cash_account_id,
                'responsible_user_id' => $responsible_user_id,
                'delivered_amount' => $delivered_amount,
                'spent_amount' => 0,
                'returned_amount' => 0,
                'status' => 'open',
                'due_date' => self::resolve_petty_cash_due_date(wp_unslash($_POST['due_date'] ?? '')),
                'evidence_json' => null,
                'notes' => $notes,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        if (!$ok) {
            wp_send_json_error(array('message' => __('No se pudo crear la rendición de caja chica.', 'aura-suite')));
        }

        wp_send_json_success(array(
            'message' => __('Entrega de fondo registrada en estado Abierta.', 'aura-suite'),
            'id' => (int) $wpdb->insert_id,
        ));
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

        $spent_amount = isset($_POST['spent_amount']) ? (float) $_POST['spent_amount'] : 0;
        $returned_amount = isset($_POST['returned_amount']) ? (float) $_POST['returned_amount'] : 0;
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        $evidence_text = sanitize_textarea_field(wp_unslash($_POST['evidence_json'] ?? ''));

        if ($spent_amount < 0 || $returned_amount < 0) {
            wp_send_json_error(array('message' => __('Los montos no pueden ser negativos.', 'aura-suite')));
        }

        $delivered = (float) $row->delivered_amount;
        $diff = abs(($spent_amount + $returned_amount) - $delivered);
        if ($diff > 0.01) {
            wp_send_json_error(array('message' => __('La regla de caja chica exige: Entregado = Gastado + Devuelto.', 'aura-suite')));
        }

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
                'spent_amount' => $spent_amount,
                'returned_amount' => $returned_amount,
                'status' => 'submitted',
                'evidence_json' => (!empty($evidence_text) || !empty($upload_result)) ? wp_json_encode($evidence_payload) : null,
                'notes' => $notes,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $id),
            array('%f', '%f', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($ok === false) {
            wp_send_json_error(array('message' => __('No se pudo enviar la rendición.', 'aura-suite')));
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
        ));
    }

    public static function ajax_list_third_parties() {
        self::check_ajax_permissions();

        $include_inactive = !empty($_POST['include_inactive']);

        wp_send_json_success(array(
            'third_parties' => self::get_third_parties($include_inactive),
        ));
    }

    public static function ajax_create_third_party() {
        self::check_ajax_permissions();

        $full_name = sanitize_text_field(wp_unslash($_POST['full_name'] ?? ''));
        $document_id = sanitize_text_field(wp_unslash($_POST['document_id'] ?? ''));
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

        if ($full_name === '') {
            wp_send_json_error(array('message' => __('Debes indicar el nombre del tercero.', 'aura-suite')));
        }

        if ($email !== '' && !is_email($email)) {
            wp_send_json_error(array('message' => __('El correo del tercero no es válido.', 'aura-suite')));
        }

        $counterparty_id = self::ensure_third_party(
            $full_name,
            array(
                'document_id' => $document_id,
                'phone' => $phone,
                'email' => $email,
                'notes' => $notes,
            ),
            get_current_user_id()
        );

        if ($counterparty_id <= 0) {
            wp_send_json_error(array('message' => __('No se pudo registrar el tercero.', 'aura-suite')));
        }

        wp_send_json_success(array(
            'id' => $counterparty_id,
            'message' => __('Tercero registrado correctamente.', 'aura-suite'),
            'third_parties' => self::get_active_third_parties(),
        ));
    }

    public static function ajax_update_third_party() {
        self::check_ajax_permissions();

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_third_parties';

        $id = absint($_POST['id'] ?? 0);
        $full_name = sanitize_text_field(wp_unslash($_POST['full_name'] ?? ''));
        $document_id = sanitize_text_field(wp_unslash($_POST['document_id'] ?? ''));
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

        if ($id <= 0) {
            wp_send_json_error(array('message' => __('Tercero inválido.', 'aura-suite')));
        }

        if ($full_name === '') {
            wp_send_json_error(array('message' => __('Debes indicar el nombre del tercero.', 'aura-suite')));
        }

        if ($email !== '' && !is_email($email)) {
            wp_send_json_error(array('message' => __('El correo del tercero no es válido.', 'aura-suite')));
        }

        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id = %d", $id));
        if ($exists <= 0) {
            wp_send_json_error(array('message' => __('El tercero no existe.', 'aura-suite')));
        }

        $ok = $wpdb->update(
            $table,
            array(
                'full_name' => $full_name,
                'document_id' => $document_id,
                'phone' => $phone,
                'email' => $email,
                'notes' => $notes,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
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

        $counterparty_id = absint($_POST['counterparty_id'] ?? 0);
        $person_user_id = absint($_POST['person_user_id'] ?? 0);
        $owed_amount = isset($_POST['owed_amount']) ? (float) $_POST['owed_amount'] : 0;
        $origin_transaction_id = absint($_POST['origin_transaction_id'] ?? 0);
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

        if ($counterparty_id <= 0 && $person_user_id > 0) {
            $person = get_user_by('id', $person_user_id);
            if ($person) {
                $counterparty_id = self::ensure_third_party(
                    (string) $person->display_name,
                    array('email' => (string) $person->user_email),
                    get_current_user_id()
                );
            }
        }

        if ($counterparty_id <= 0 || !self::get_third_party_by_id($counterparty_id)) {
            wp_send_json_error(array('message' => __('Debes seleccionar un tercero válido para el reembolso.', 'aura-suite')));
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

        if ($id <= 0 || $paying_account_id <= 0 || $payment_amount <= 0) {
            wp_send_json_error(array('message' => __('Datos inválidos para registrar el pago del reembolso.', 'aura-suite')));
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

        $movement_id = self::register_account_movement(array(
            'account_id' => $paying_account_id,
            'movement_type' => 'debit',
            'amount' => $payment_amount,
            'reference_type' => 'reimbursement',
            'reference_id' => $id,
            'notes' => $notes,
        ));

        if (!$movement_id) {
            wp_send_json_error(array('message' => __('No se pudo descontar el pago desde la cuenta seleccionada.', 'aura-suite')));
        }

        $new_paid = round((float) $row->paid_amount + $payment_amount, 2);
        $new_status = self::resolve_reimbursement_status((float) $row->owed_amount, $new_paid, (string) $row->status);

        $ok = $wpdb->update(
            $table,
            array(
                'paid_amount' => $new_paid,
                'status' => $new_status,
                'paying_account_id' => $paying_account_id,
                'notes' => $notes !== '' ? $notes : $row->notes,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $id),
            array('%f', '%s', '%d', '%s', '%s'),
            array('%d')
        );

        if ($ok === false) {
            wp_send_json_error(array('message' => __('Se descontó el pago, pero no se pudo actualizar el estado del reembolso.', 'aura-suite')));
        }

        wp_send_json_success(array(
            'message' => __('Pago de reembolso registrado correctamente.', 'aura-suite'),
            'status' => $new_status,
            'paid_amount' => $new_paid,
            'remaining' => max(0, round((float) $row->owed_amount - $new_paid, 2)),
        ));
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

    private static function get_third_parties($include_inactive = false) {
        global $wpdb;

        $table = $wpdb->prefix . 'aura_finance_third_parties';
        $users = $wpdb->users;
        $where = $include_inactive ? '1=1' : 'tp.is_active = 1';

        return $wpdb->get_results(
            "SELECT tp.id, tp.full_name, tp.document_id, tp.phone, tp.email, tp.notes, tp.is_active, tp.wp_user_id,
                    u.user_login AS wp_user_login, u.display_name AS wp_user_display
             FROM {$table} tp
             LEFT JOIN {$users} u ON u.ID = tp.wp_user_id
             WHERE {$where}
             ORDER BY tp.full_name ASC
             LIMIT 1000",
            ARRAY_A
        );
    }

    private static function get_active_third_parties() {
        return self::get_third_parties(false);
    }

    private static function get_third_party_by_id($id) {
        $id = absint($id);
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_third_parties';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT id, full_name FROM {$table} WHERE id = %d AND is_active = 1", $id),
            ARRAY_A
        );
    }

    private static function ensure_third_party($full_name, $extra = array(), $created_by = 0) {
        $full_name = sanitize_text_field((string) $full_name);
        if ($full_name === '') {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_third_parties';

        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE LOWER(full_name) = LOWER(%s) AND is_active = 1 LIMIT 1",
            $full_name
        ));

        if ($existing_id > 0) {
            return $existing_id;
        }

        $created_by = absint($created_by);
        $inserted = $wpdb->insert(
            $table,
            array(
                'full_name' => $full_name,
                'document_id' => sanitize_text_field((string) ($extra['document_id'] ?? '')),
                'phone' => sanitize_text_field((string) ($extra['phone'] ?? '')),
                'email' => sanitize_email((string) ($extra['email'] ?? '')),
                'notes' => sanitize_textarea_field((string) ($extra['notes'] ?? '')),
                'is_active' => 1,
                'created_by' => $created_by > 0 ? $created_by : get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
        );

        if (!$inserted) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public static function scan_overdue_petty_cash_settlements() {
        global $wpdb;

        $table = $wpdb->prefix . 'aura_finance_petty_cash_settlements';
        $accounts = $wpdb->prefix . 'aura_finance_accounts';
        $users = $wpdb->users;

        $rows = $wpdb->get_results(
            "SELECT s.id, s.petty_cash_account_id, s.responsible_user_id, s.delivered_amount, s.due_date,
                    s.status, s.notes, s.last_overdue_alert_at,
                    a.name AS account_name,
                    u.display_name AS responsible_name,
                    u.user_email AS responsible_email
             FROM {$table} s
             INNER JOIN {$accounts} a ON a.id = s.petty_cash_account_id
             LEFT JOIN {$users} u ON u.ID = s.responsible_user_id
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
        $subject = sprintf(
            __('[Aura Suite] Rendición vencida #%d', 'aura-suite'),
            (int) ($row['id'] ?? 0)
        );

        $message = sprintf(
            "Rendición vencida detectada.\n\nID: #%d\nCuenta: %s\nResponsable: %s\nEstado: %s\nVence: %s\nEntregado: %s\n\nRevisa en: %s",
            (int) ($row['id'] ?? 0),
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

    private static function resolve_petty_cash_due_date($raw_due_date) {
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
        $dt->modify('+' . self::PETTY_CASH_DEFAULT_DUE_DAYS . ' days');
        return $dt->format('Y-m-d H:i:s');
    }

    private static function handle_petty_cash_evidence_uploads($input_name) {
        if (empty($_FILES[$input_name]) || empty($_FILES[$input_name]['name'])) {
            return array();
        }

        if (!current_user_can('upload_files')) {
            return new WP_Error('petty_upload_perm', __('No tienes permisos para subir archivos de evidencia.', 'aura-suite'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $files = $_FILES[$input_name];
        $uploaded = array();

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

            $_FILES['aura_petty_evidence_single'] = $single;
            $attachment_id = media_handle_upload('aura_petty_evidence_single', 0);
            unset($_FILES['aura_petty_evidence_single']);

            if (is_wp_error($attachment_id)) {
                return new WP_Error('petty_upload_file', sprintf(__('No se pudo subir el archivo "%s".', 'aura-suite'), sanitize_file_name($single['name'])));
            }

            $uploaded[] = array(
                'id' => (int) $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'name' => sanitize_file_name($single['name']),
            );
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

        if ($envelope) {
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
        }

        wp_send_json_success(array(
            'year' => $year,
            'envelope' => $envelope,
            'months' => array_values($months),
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

        wp_send_json_success(array('message' => __('Presupuesto guardado correctamente.', 'aura-suite')));
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
        $errors = array();

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            if (!self::row_has_content($row)) {
                continue;
            }

            $year = (int) self::cell_by_aliases($row, $header_map, array('year', 'fiscal_year', 'ano', 'anio', 'año'));
            $annual_limit = (float) self::cell_by_aliases($row, $header_map, array('annual_limit', 'presupuesto_anual', 'tope_anual', 'anual'));
            $policy_raw = (string) self::cell_by_aliases($row, $header_map, array('exceed_policy', 'policy', 'politica', 'politica_exceso'));

            if ($year < 2000 || $year > 2100) {
                $errors[] = sprintf(__('Fila %d: año fiscal inválido.', 'aura-suite'), $line);
                continue;
            }

            if ($annual_limit < 0) {
                $errors[] = sprintf(__('Fila %d: el presupuesto anual no puede ser negativo.', 'aura-suite'), $line);
                continue;
            }

            $monthly = self::extract_monthly_limits($row, $header_map);
            $monthly_total = array_sum($monthly);
            if ($monthly_total > $annual_limit) {
                $errors[] = sprintf(__('Fila %d: la suma mensual supera el presupuesto anual.', 'aura-suite'), $line);
                continue;
            }

            $policy = self::normalize_policy($policy_raw);
            $upsert = self::upsert_budget_row($year, $annual_limit, $policy, $monthly);
            if (is_wp_error($upsert)) {
                $errors[] = sprintf(__('Fila %d: %s', 'aura-suite'), $line, $upsert->get_error_message());
                continue;
            }

            $imported++;
        }

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
        $annual_limit = (float) self::mapped_or_alias_value($row, $header_map, $mapping, 'annual_limit', array('annual_limit', 'presupuesto_anual', 'tope_anual', 'anual'));
        $policy_raw = (string) self::mapped_or_alias_value($row, $header_map, $mapping, 'exceed_policy', array('exceed_policy', 'policy', 'politica', 'politica_exceso'));

        if ($year < 2000 || $year > 2100) {
            return new WP_Error('budget_row', sprintf(__('Fila %d: año fiscal inválido.', 'aura-suite'), $line));
        }

        if ($annual_limit < 0) {
            return new WP_Error('budget_row', sprintf(__('Fila %d: el presupuesto anual no puede ser negativo.', 'aura-suite'), $line));
        }

        $monthly = self::extract_monthly_limits($row, $header_map, $mapping);
        if (array_sum($monthly) > $annual_limit) {
            return new WP_Error('budget_row', sprintf(__('Fila %d: la suma mensual supera el presupuesto anual.', 'aura-suite'), $line));
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

        $annual_spent = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM {$transactions_table}
             WHERE transaction_type = 'expense'
               AND status = 'approved'
               AND deleted_at IS NULL
               AND YEAR(transaction_date) = %d",
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
            "SELECT MONTH(transaction_date) AS month_num, COALESCE(SUM(amount),0) AS spent
             FROM {$transactions_table}
             WHERE transaction_type = 'expense'
               AND status = 'approved'
               AND deleted_at IS NULL
               AND YEAR(transaction_date) = %d
             GROUP BY MONTH(transaction_date)",
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

    private static function check_ajax_permissions() {
        check_ajax_referer('aura_financial_accounts_nonce', 'nonce');

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

        if (function_exists('mb_detect_encoding')) {
            $enc = mb_detect_encoding($content, array('UTF-8', 'ISO-8859-1', 'Windows-1252'), true);
            if ($enc && $enc !== 'UTF-8') {
                $content = mb_convert_encoding($content, 'UTF-8', $enc);
                file_put_contents($filepath, $content);
            }
        }

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
