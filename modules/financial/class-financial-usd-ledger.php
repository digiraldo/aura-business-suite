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
    // MENÚ
    // ─────────────────────────────────────────────────────────────────

    public static function add_menu() {
        add_submenu_page(
            'aura-financial-dashboard',
            __( 'Caja Chica USD', 'aura-suite' ),
            __( 'Caja USD', 'aura-suite' ),
            'aura_finance_view_all',
            'aura-financial-usd-ledger',
            [ __CLASS__, 'render_page' ]
        );
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

        wp_enqueue_script(
            'aura-usd-ledger',
            AURA_PLUGIN_URL . 'assets/js/usd-ledger.js',
            [ 'jquery' ],
            AURA_VERSION,
            true
        );

        wp_localize_script( 'aura-usd-ledger', 'auraUSD', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'adminUrl' => admin_url(),
            'nonce'    => wp_create_nonce( 'aura_usd_ledger_nonce' ),
            'i18n'     => [
                'opening'           => __( 'Apertura', 'aura-suite' ),
                'deposit'           => __( 'Depósito', 'aura-suite' ),
                'conversion'        => __( 'Conversión', 'aura-suite' ),
                'confirmDelete'     => __( '¿Eliminar esta entrada? Se revertirá el saldo USD pero NO se eliminará la transacción MXN creada.', 'aura-suite' ),
                'insufficientFunds' => __( 'Saldo USD insuficiente.', 'aura-suite' ),
                'saving'            => __( 'Guardando...', 'aura-suite' ),
                'success'           => __( '¡Operación exitosa!', 'aura-suite' ),
                'error'             => __( 'Error al procesar. Intenta nuevamente.', 'aura-suite' ),
            ],
        ] );
    }

    // ─────────────────────────────────────────────────────────────────
    // RENDER PAGE
    // ─────────────────────────────────────────────────────────────────

    public static function render_page() {
        if ( ! current_user_can( 'aura_finance_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
        }

        // Auto-crear tabla si aún no existe
        if ( self::needs_install() ) {
            self::create_table();
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
        if ( ! current_user_can( 'aura_finance_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permiso.', 'aura-suite' ) ], 403 );
        }

        $usd_amount  = round( (float) ( $_POST['usd_amount'] ?? 0 ), 4 );
        $entry_type  = sanitize_text_field( $_POST['entry_type'] ?? 'deposit' );
        $notes       = sanitize_textarea_field( $_POST['notes'] ?? '' );
        $entry_date  = sanitize_text_field( $_POST['entry_date'] ?? '' );

        if ( $usd_amount <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'El monto debe ser mayor a cero.', 'aura-suite' ) ] );
        }
        if ( ! in_array( $entry_type, [ 'opening', 'deposit' ], true ) ) {
            $entry_type = 'deposit';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_usd_ledger';

        $data = [
            'entry_type'  => $entry_type,
            'usd_amount'  => $usd_amount,
            'notes'       => $notes,
            'created_by'  => get_current_user_id(),
            'created_at'  => $entry_date ? date( 'Y-m-d H:i:s', strtotime( $entry_date ) ) : current_time( 'mysql' ),
        ];

        $result = $wpdb->insert( $table, $data, [ '%s','%f','%s','%d','%s' ] );

        if ( false === $result ) {
            wp_send_json_error( [ 'message' => __( 'Error al guardar en la base de datos.', 'aura-suite' ) ] );
        }

        wp_send_json_success( [
            'message' => $entry_type === 'opening'
                ? __( 'Saldo inicial registrado.', 'aura-suite' )
                : __( 'Depósito USD registrado.', 'aura-suite' ),
            'balance' => self::get_current_balance(),
            'id'      => $wpdb->insert_id,
        ] );
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX — REGISTRAR CONVERSIÓN USD → MXN
    // ─────────────────────────────────────────────────────────────────

    public static function ajax_convert() {
        check_ajax_referer( 'aura_usd_ledger_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permiso.', 'aura-suite' ) ], 403 );
        }

        $usd_amount    = round( (float) ( $_POST['usd_amount'] ?? 0 ), 4 );
        $exchange_rate = round( (float) ( $_POST['exchange_rate'] ?? 0 ), 4 );
        $notes         = sanitize_textarea_field( $_POST['notes'] ?? '' );
        $conv_date     = sanitize_text_field( $_POST['conversion_date'] ?? '' );

        if ( $usd_amount <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'El monto USD debe ser mayor a cero.', 'aura-suite' ) ] );
        }
        if ( $exchange_rate <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'El tipo de cambio debe ser mayor a cero.', 'aura-suite' ) ] );
        }

        $balance = self::get_current_balance();
        if ( $usd_amount > $balance ) {
            wp_send_json_error( [
                'message' => sprintf(
                    __( 'Saldo insuficiente. Disponible: $%.4f USD.', 'aura-suite' ),
                    $balance
                ),
            ] );
        }

        $mxn_amount  = round( $usd_amount * $exchange_rate, 2 );
        $date_mysql  = $conv_date ? date( 'Y-m-d', strtotime( $conv_date ) ) : current_time( 'Y-m-d' );
        $date_display = date( 'd/m/Y', strtotime( $date_mysql ) );

        // 1. Crear transacción de ingreso en MXN
        global $wpdb;
        $tx_table  = $wpdb->prefix . 'aura_finance_transactions';
        $cat_id    = self::get_or_create_category();

        $description = sprintf(
            __( 'Conversión $%.2f USD × $%.4f = $%.2f MXN', 'aura-suite' ),
            $usd_amount, $exchange_rate, $mxn_amount
        );
        if ( $notes ) {
            $description .= ' — ' . $notes;
        }

        $tx_result = $wpdb->insert( $tx_table, [
            'transaction_date' => $date_mysql,
            'transaction_type' => 'income',
            'category_id'      => $cat_id,
            'amount'           => $mxn_amount,
            'description'      => $description,
            'payment_method'   => 'efectivo',
            'status'           => 'approved',
            'created_by'       => get_current_user_id(),
            'created_at'       => current_time( 'mysql' ),
        ], [ '%s','%s','%d','%f','%s','%s','%s','%d','%s' ] );

        $tx_id = $tx_result ? $wpdb->insert_id : null;

        // 2. Registrar en el ledger USD
        $ledger_table = $wpdb->prefix . 'aura_finance_usd_ledger';
        $wpdb->insert( $ledger_table, [
            'entry_type'     => 'conversion',
            'usd_amount'     => $usd_amount,
            'exchange_rate'  => $exchange_rate,
            'mxn_amount'     => $mxn_amount,
            'transaction_id' => $tx_id,
            'notes'          => $notes,
            'created_by'     => get_current_user_id(),
            'created_at'     => $date_mysql . ' ' . current_time( 'H:i:s' ),
        ], [ '%s','%f','%f','%f','%d','%s','%d','%s' ] );

        // Disparar evento para invalidar caché del dashboard
        do_action( 'aura_finance_transaction_created', $tx_id );

        wp_send_json_success( [
            'message'       => sprintf(
                __( 'Conversión registrada: $%.2f USD → $%.2f MXN', 'aura-suite' ),
                $usd_amount, $mxn_amount
            ),
            'balance'       => self::get_current_balance(),
            'mxn_amount'    => $mxn_amount,
            'transaction_id' => $tx_id,
        ] );
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX — HISTORIAL
    // ─────────────────────────────────────────────────────────────────

    public static function ajax_get_history() {
        check_ajax_referer( 'aura_usd_ledger_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permiso.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_usd_ledger';
        $users = $wpdb->prefix . 'users';

        $rows = $wpdb->get_results(
            "SELECT l.*, u.display_name
             FROM {$table} l
             LEFT JOIN {$users} u ON u.ID = l.created_by
             ORDER BY l.created_at DESC
             LIMIT 200"
        );

        $balance = self::get_current_balance();

        wp_send_json_success( [
            'rows'    => $rows,
            'balance' => $balance,
        ] );
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX — ELIMINAR ENTRADA
    // ─────────────────────────────────────────────────────────────────

    public static function ajax_delete_entry() {
        check_ajax_referer( 'aura_usd_ledger_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Solo administradores pueden eliminar entradas.', 'aura-suite' ) ], 403 );
        }

        $id = (int) ( $_POST['entry_id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_usd_ledger';
        $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

        wp_send_json_success( [
            'message' => __( 'Entrada eliminada del ledger USD.', 'aura-suite' ),
            'balance' => self::get_current_balance(),
        ] );
    }
}
