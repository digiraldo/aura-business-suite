<?php
/**
 * Libro Mayor por Usuario (Fase 6, Item 6.3)
 *
 * Proporciona una vista completa de todas las transacciones vinculadas a un
 * usuario específico, con balance acumulativo corriente, filtros por fecha y
 * concepto, toggle de estado y exportación CSV.
 *
 * Capability requerida: aura_finance_user_ledger
 * Slug de página: aura-user-ledger
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Financial_User_Ledger {

    // -------------------------------------------------------------------------
    // Constantes
    // -------------------------------------------------------------------------

    const PER_PAGE = 50;

    // -------------------------------------------------------------------------
    // Inicialización
    // -------------------------------------------------------------------------

    public static function init(): void {
        // AJAX: exportar CSV del libro mayor
        add_action( 'wp_ajax_aura_export_ledger_csv', [ __CLASS__, 'ajax_export_ledger_csv' ] );
    }

    // -------------------------------------------------------------------------
    // Renderizado de la página
    // -------------------------------------------------------------------------

    /**
     * Punto de entrada para la página admin — verifica capacidad e incluye template.
     */
    public static function render(): void {
        if (
            ! current_user_can( 'aura_finance_user_ledger' ) &&
            ! current_user_can( 'aura_finance_view_all' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'No tienes permisos para acceder al Libro Mayor.', 'aura-suite' ) );
        }

        include AURA_PLUGIN_DIR . 'templates/financial/user-ledger.php';
    }

    // -------------------------------------------------------------------------
    // Lógica de datos
    // -------------------------------------------------------------------------

    /**
     * Obtener filas del libro mayor para un usuario, ordenadas cronológicamente
     * (ASC) para poder calcular el balance acumulativo en PHP.
     *
     * @param int   $user_id   ID del usuario relacionado.
     * @param array $filters   Claves: date_from, date_to, concept, show_all (bool), paged.
     * @return array           Objetos de la BD. Balance acumulativo añadido como ->running_balance.
     */
    public static function get_ledger_rows( int $user_id, array $filters = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        // Cláusulas WHERE
        $where = $wpdb->prepare(
            'related_user_id = %d AND deleted_at IS NULL',
            $user_id
        );

        if ( ! empty( $filters['date_from'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date >= %s', $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date <= %s', $filters['date_to'] );
        }
        if ( ! empty( $filters['concept'] ) ) {
            $where .= $wpdb->prepare( ' AND related_user_concept = %s', $filters['concept'] );
        }

        // Por defecto solo aprobadas; toggle "show_all" muestra todas
        $show_all = ! empty( $filters['show_all'] );
        if ( ! $show_all ) {
            $where .= " AND status = 'approved'";
        }

        // Paginación
        $paged  = max( 1, (int) ( $filters['paged'] ?? 1 ) );
        $offset = ( $paged - 1 ) * self::PER_PAGE;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT id, transaction_type, amount, description, transaction_date,
                    status, related_user_concept, recipient_payer, reference_number,
                    created_by
             FROM {$table}
             WHERE {$where}
             ORDER BY transaction_date ASC, id ASC
             LIMIT " . self::PER_PAGE . " OFFSET {$offset}"
        );

        if ( empty( $rows ) ) {
            return [];
        }

        // -----------------------------------------------------------------------
        // Calcular balance acumulativo corriente.
        // Para ello necesitamos el balance acumulado de páginas anteriores.
        // Si estamos en paged > 1 obtenemos la suma de todas las filas anteriores.
        // -----------------------------------------------------------------------
        $prior_balance = 0.0;
        if ( $paged > 1 ) {
            $prior_balance = self::get_balance_before_page( $user_id, $filters, $paged );
        }

        $running = $prior_balance;
        foreach ( $rows as $row ) {
            $amount = (float) $row->amount;
            // Perspectiva usuario: egreso org → usuario = ingreso usuario
            if ( $row->transaction_type === 'expense' ) {
                $running += $amount;
            } else {
                $running -= $amount;
            }
            $row->running_balance = $running;
        }

        return $rows;
    }

    /**
     * Obtener el balance acumulado de todas las filas que preceden a la página actual.
     * Se usa para que el balance acumulativo sea correcto en páginas > 1.
     *
     * @param int   $user_id
     * @param array $filters
     * @param int   $paged   Página actual (> 1).
     * @return float
     */
    private static function get_balance_before_page( int $user_id, array $filters, int $paged ): float {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $where = $wpdb->prepare(
            'related_user_id = %d AND deleted_at IS NULL',
            $user_id
        );

        if ( ! empty( $filters['date_from'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date >= %s', $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date <= %s', $filters['date_to'] );
        }
        if ( ! empty( $filters['concept'] ) ) {
            $where .= $wpdb->prepare( ' AND related_user_concept = %s', $filters['concept'] );
        }
        $show_all = ! empty( $filters['show_all'] );
        if ( ! $show_all ) {
            $where .= " AND status = 'approved'";
        }

        $prior_limit = ( $paged - 1 ) * self::PER_PAGE;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $prior_rows = $wpdb->get_results(
            "SELECT transaction_type, amount
             FROM {$table}
             WHERE {$where}
             ORDER BY transaction_date ASC, id ASC
             LIMIT {$prior_limit} OFFSET 0"
        );

        $balance = 0.0;
        foreach ( $prior_rows as $r ) {
            // Perspectiva usuario: egreso org → usuario = ingreso usuario
            if ( $r->transaction_type === 'expense' ) {
                $balance += (float) $r->amount;
            } else {
                $balance -= (float) $r->amount;
            }
        }

        return $balance;
    }

    /**
     * Contar el total de filas del libro mayor para paginación.
     *
     * @param int   $user_id
     * @param array $filters
     * @return int
     */
    public static function count_ledger_rows( int $user_id, array $filters = [] ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $where = $wpdb->prepare(
            'related_user_id = %d AND deleted_at IS NULL',
            $user_id
        );

        if ( ! empty( $filters['date_from'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date >= %s', $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date <= %s', $filters['date_to'] );
        }
        if ( ! empty( $filters['concept'] ) ) {
            $where .= $wpdb->prepare( ' AND related_user_concept = %s', $filters['concept'] );
        }

        $show_all = ! empty( $filters['show_all'] );
        if ( ! $show_all ) {
            $where .= " AND status = 'approved'";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE {$where}"
        );
    }

    /**
     * Obtener totales del período: total ingresos, total egresos, balance neto
     * (siempre del período completo, ignorando paginación).
     *
     * @param int   $user_id
     * @param array $filters
     * @return array { income: float, expense: float, net: float }
     */
    public static function get_ledger_totals( int $user_id, array $filters = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $where = $wpdb->prepare(
            'related_user_id = %d AND deleted_at IS NULL',
            $user_id
        );

        if ( ! empty( $filters['date_from'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date >= %s', $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date <= %s', $filters['date_to'] );
        }
        if ( ! empty( $filters['concept'] ) ) {
            $where .= $wpdb->prepare( ' AND related_user_concept = %s', $filters['concept'] );
        }

        $show_all = ! empty( $filters['show_all'] );
        if ( ! $show_all ) {
            $where .= " AND status = 'approved'";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT transaction_type, SUM(amount) AS total
             FROM {$table}
             WHERE {$where}
             GROUP BY transaction_type"
        );

        $income  = 0.0;
        $expense = 0.0;

        foreach ( $rows as $row ) {
            // Perspectiva usuario: egreso org → usuario = ingreso usuario
            if ( $row->transaction_type === 'expense' ) {
                $income = (float) $row->total;
            } else {
                $expense = (float) $row->total;
            }
        }

        return [
            'income'  => $income,
            'expense' => $expense,
            'net'     => $income - $expense,
        ];
    }

    // -------------------------------------------------------------------------
    // AJAX: Exportar CSV
    // -------------------------------------------------------------------------

    /**
     * AJAX handler — descarga CSV del libro mayor completo del usuario.
     * Responde a: wp_ajax_aura_export_ledger_csv
     */
    public static function ajax_export_ledger_csv(): void {
        check_ajax_referer( 'aura_transaction_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_finance_user_ledger' ) &&
            ! current_user_can( 'aura_finance_view_all' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'Sin permisos.', 'aura-suite' ) );
        }

        $user_id = intval( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_die( __( 'Usuario requerido.', 'aura-suite' ) );
        }

        $filters = [
            'date_from' => sanitize_text_field( $_POST['date_from'] ?? '' ),
            'date_to'   => sanitize_text_field( $_POST['date_to']   ?? '' ),
            'concept'   => sanitize_key( $_POST['concept']           ?? '' ),
            'show_all'  => ! empty( $_POST['show_all'] ),
        ];

        // Obtener TODAS las filas (sin paginación) para el CSV
        $all_rows = self::get_all_ledger_rows_for_csv( $user_id, $filters );
        $user_obj = get_userdata( $user_id );
        $currency = get_option( 'aura_currency_symbol', '$' );
        $concepts = self::get_concepts_labels();

        $filename = 'libro-mayor-' . sanitize_file_name( $user_obj ? $user_obj->user_login : $user_id ) . '-' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        echo "\xEF\xBB\xBF"; // BOM UTF-8

        $out = fopen( 'php://output', 'w' );

        // Encabezado
        fputcsv( $out, [
            'Fecha',
            'Descripción',
            'Concepto',
            'Ingreso (' . $currency . ')',
            'Egreso (' . $currency . ')',
            'Balance Acumulativo (' . $currency . ')',
            'Estado',
        ], ';' );

        foreach ( $all_rows as $row ) {
            $is_income        = $row->transaction_type === 'expense'; // Perspectiva usuario: egreso org = ingreso usuario
            $income_col       = $is_income  ? number_format( (float) $row->amount, 2, '.', '' ) : '';
            $expense_col      = ! $is_income ? number_format( (float) $row->amount, 2, '.', '' ) : '';
            $balance_col      = number_format( (float) $row->running_balance, 2, '.', '' );

            fputcsv( $out, [
                $row->transaction_date,
                $row->description,
                $concepts[ $row->related_user_concept ] ?? $row->related_user_concept,
                $income_col,
                $expense_col,
                $balance_col,
                ucfirst( $row->status ),
            ], ';' );
        }

        fclose( $out );
        exit;
    }

    /**
     * Obtener todas las filas para CSV (sin paginación, con balance corriente).
     *
     * @param int   $user_id
     * @param array $filters
     * @return array
     */
    private static function get_all_ledger_rows_for_csv( int $user_id, array $filters = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $where = $wpdb->prepare(
            'related_user_id = %d AND deleted_at IS NULL',
            $user_id
        );

        if ( ! empty( $filters['date_from'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date >= %s', $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date <= %s', $filters['date_to'] );
        }
        if ( ! empty( $filters['concept'] ) ) {
            $where .= $wpdb->prepare( ' AND related_user_concept = %s', $filters['concept'] );
        }

        $show_all = ! empty( $filters['show_all'] );
        if ( ! $show_all ) {
            $where .= " AND status = 'approved'";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT id, transaction_type, amount, description, transaction_date,
                    status, related_user_concept
             FROM {$table}
             WHERE {$where}
             ORDER BY transaction_date ASC, id ASC"
        );

        if ( empty( $rows ) ) {
            return [];
        }

        $running = 0.0;
        foreach ( $rows as $row ) {
            $amount = (float) $row->amount;
            // Perspectiva usuario: egreso org → usuario = ingreso usuario
            if ( $row->transaction_type === 'expense' ) {
                $running += $amount;
            } else {
                $running -= $amount;
            }
            $row->running_balance = $running;
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Mapa de conceptos de vinculación de usuario → etiqueta legible.
     */
    public static function get_concepts_labels(): array {
        return [
            'payment_to_user'       => __( 'Pago realizado a un usuario', 'aura-suite' ),
            'charge_to_user'        => __( 'Cobro realizado a un usuario', 'aura-suite' ),
            'salary'                => __( 'Pago de salario/nómina', 'aura-suite' ),
            'scholarship'           => __( 'Beca asignada', 'aura-suite' ),
            'loan_payment'          => __( 'Pago de préstamo', 'aura-suite' ),
            'refund'                => __( 'Reembolso', 'aura-suite' ),
            'expense_reimbursement' => __( 'Reembolso de gastos', 'aura-suite' ),
        ];
    }
}
