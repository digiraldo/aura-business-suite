<?php
/**
 * Caja Chica USD — Ledger de dólares con conversión a MXN
 *
 * Permite registrar el saldo inicial en USD, registrar conversiones
 * parciales o totales a pesos mexicanos (MXN), y crea automáticamente
 * una transacción de ingreso en el módulo financiero por cada conversión.
 *
 * Tabla: wp_aura_finance_usd_ledger
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Financial_USD_Ledger {

    const DB_VERSION_OPTION = 'aura_usd_ledger_db_version';
    const DB_VERSION        = '1.0.0';
    const CATEGORY_SLUG     = 'conversion-usd-mxn';

    // ─────────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────────

    public static function init() {
        add_action( 'admin_menu',              [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init',              [ __CLASS__, 'handle_legacy_page_redirect' ] );
        add_action( 'admin_enqueue_scripts',   [ __CLASS__, 'enqueue_assets' ] );

        // AJAX — saldo
        add_action( 'wp_ajax_aura_usd_get_balance',    [ __CLASS__, 'ajax_get_balance' ] );
        add_action( 'wp_ajax_aura_usd_set_balance',    [ __CLASS__, 'ajax_set_balance' ] );
        // AJAX — conversión
        add_action( 'wp_ajax_aura_usd_convert',        [ __CLASS__, 'ajax_convert' ] );
        // AJAX — historial
        add_action( 'wp_ajax_aura_usd_get_history',    [ __CLASS__, 'ajax_get_history' ] );
        // AJAX — eliminar entrada
        add_action( 'wp_ajax_aura_usd_delete_entry',   [ __CLASS__, 'ajax_delete_entry' ] );
    }

    // ─────────────────────────────────────────────────────────────────
    // PÁGINA LEGACY OCULTA (sin botón de menú)
    // ─────────────────────────────────────────────────────────────────

    public static function add_menu() {
        add_submenu_page(
            null,
            __( 'Caja Chica USD', 'aura-suite' ),
            __( 'Caja USD', 'aura-suite' ),
            'aura_finance_view_all',
            'aura-financial-usd-ledger',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function handle_legacy_page_redirect() {
        if ( ! is_admin() ) {
            return;
        }

        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'aura-financial-usd-ledger' !== $page ) {
            return;
        }

        $target_url = admin_url( 'admin.php?page=aura-financial-accounts&account_type=usd_cash&currency=USD' );
        wp_safe_redirect( $target_url );
        exit;
    }

    // ─────────────────────────────────────────────────────────────────
    // TABLA DB
    // ─────────────────────────────────────────────────────────────────

    public static function create_table() {
        global $wpdb;
        $table      = $wpdb->prefix . 'aura_finance_usd_ledger';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_type      ENUM('opening','deposit','conversion') NOT NULL DEFAULT 'conversion',
            usd_amount      DECIMAL(15,4) NOT NULL DEFAULT 0,
            exchange_rate   DECIMAL(10,4) NULL COMMENT 'MXN por 1 USD',
            mxn_amount      DECIMAL(15,2) NULL COMMENT 'MXN obtenidos (usd_amount * exchange_rate)',
            transaction_id  BIGINT UNSIGNED NULL COMMENT 'ID en wp_aura_finance_transactions',
            notes           TEXT NULL,
            created_by      BIGINT UNSIGNED NOT NULL,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_entry_type (entry_type),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    public static function needs_install() {
        return get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION;
    }

    // ─────────────────────────────────────────────────────────────────
    // ASSETS
    // ─────────────────────────────────────────────────────────────────

    public static function enqueue_assets( $hook ) {
        if ( ! in_array( $hook, [
            'finanzas_page_aura-financial-usd-ledger',
            'toplevel_page_aura-financial-usd-ledger',
        ], true ) ) {
            return;
        }

        wp_enqueue_style(
            'aura-usd-ledger',
            AURA_PLUGIN_URL . 'assets/css/usd-ledger.css',
            [],
            AURA_VERSION
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // RENDER PAGE
    // ─────────────────────────────────────────────────────────────────

    public static function render_page() {
        if ( ! current_user_can( 'aura_finance_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
        }

        if ( class_exists( 'Aura_Financial_Accounts' ) ) {
            Aura_Financial_Accounts::migrate_usd_ledger_to_accounts();
        }

        include AURA_PLUGIN_DIR . 'templates/financial/usd-ledger.php';
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS — SALDO
    // ─────────────────────────────────────────────────────────────────

    /**
     * Calcula el saldo USD actual sumando aperturas/depósitos y restando conversiones.
     */
    public static function get_current_balance() {
        if ( class_exists( 'Aura_Financial_Accounts' ) ) {
            $account = Aura_Financial_Accounts::get_usd_cash_account();
            if ( $account ) {
                return round( (float) $account->current_balance, 4 );
            }
        }

        return self::get_legacy_balance();
    }

    private static function get_legacy_balance() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_usd_ledger';

        $in  = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(usd_amount),0) FROM {$table}
             WHERE entry_type IN ('opening','deposit')"
        );
        $out = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(usd_amount),0) FROM {$table}
             WHERE entry_type = 'conversion'"
        );

        return round( $in - $out, 4 );
    }

    public static function get_transition_summary() {
        $summary = class_exists( 'Aura_Financial_Accounts' )
            ? get_option( Aura_Financial_Accounts::USD_LEDGER_MIGRATION_OPTION, [] )
            : [];

        return is_array( $summary ) ? $summary : [];
    }

    public static function get_unified_history( $limit = 120 ) {
        global $wpdb;

        if ( ! class_exists( 'Aura_Financial_Accounts' ) ) {
            return [];
        }

        $account = Aura_Financial_Accounts::get_usd_cash_account();
        if ( ! $account ) {
            return [];
        }

        $movements = $wpdb->prefix . 'aura_finance_account_movements';
        $users     = $wpdb->users;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT m.*, u.display_name
             FROM {$movements} m
             LEFT JOIN {$users} u ON u.ID = m.created_by
             WHERE m.account_id = %d
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT %d",
            (int) $account->id,
            (int) $limit
        ) );
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS — CATEGORÍA MXN
    // ─────────────────────────────────────────────────────────────────

    private static function get_or_create_category() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
            self::CATEGORY_SLUG
        ) );
        if ( $id ) return (int) $id;

        $wpdb->insert( $table, [
            'name'        => __( 'Conversión USD → MXN', 'aura-suite' ),
            'slug'        => self::CATEGORY_SLUG,
            'type'        => 'income',
            'color'       => '#2ecc71',
            'is_active'   => 1,
            'created_by'  => get_current_user_id() ?: 1,
            'created_at'  => current_time( 'mysql' ),
        ], [ '%s','%s','%s','%s','%d','%d','%s' ] );

        return (int) $wpdb->insert_id;
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX — OBTENER SALDO
    // ─────────────────────────────────────────────────────────────────

    public static function ajax_get_balance() {
        check_ajax_referer( 'aura_usd_ledger_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permiso.', 'aura-suite' ) ], 403 );
        }
        wp_send_json_success( [ 'balance' => self::get_current_balance() ] );
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX — ESTABLECER SALDO INICIAL / DEPÓSITO
    // ─────────────────────────────────────────────────────────────────

    public static function ajax_set_balance() {
        check_ajax_referer( 'aura_usd_ledger_nonce', 'nonce' );
        wp_send_json_error( [ 'message' => __( 'Caja USD está en modo solo lectura. Usa Bancos para seguir operando.', 'aura-suite' ) ], 400 );
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX — REGISTRAR CONVERSIÓN USD → MXN
    // ─────────────────────────────────────────────────────────────────

    public static function ajax_convert() {
        check_ajax_referer( 'aura_usd_ledger_nonce', 'nonce' );
        wp_send_json_error( [ 'message' => __( 'Caja USD está en modo solo lectura. Usa Bancos para seguir operando.', 'aura-suite' ) ], 400 );
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX — HISTORIAL
    // ─────────────────────────────────────────────────────────────────

    public static function ajax_get_history() {
        check_ajax_referer( 'aura_usd_ledger_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permiso.', 'aura-suite' ) ], 403 );
        }

        wp_send_json_success( [
            'rows'    => self::get_unified_history( 200 ),
            'balance' => self::get_current_balance(),
        ] );
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX — ELIMINAR ENTRADA
    // ─────────────────────────────────────────────────────────────────

    public static function ajax_delete_entry() {
        check_ajax_referer( 'aura_usd_ledger_nonce', 'nonce' );
        wp_send_json_error( [ 'message' => __( 'La eliminación desde Caja USD legacy está deshabilitada. Usa Bancos para administrar movimientos nuevos.', 'aura-suite' ) ], 400 );
    }
}
