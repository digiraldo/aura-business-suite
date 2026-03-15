<?php
/**
 * Dashboard Financiero Personal del Usuario (Fase 6, Item 6.2)
 *
 * Proporciona:
 * - Widget en el Panel de WordPress con resumen personal
 * - Página dedicada /wp-admin/admin.php?page=aura-my-finance
 * - AJAX para carga dinámica del resumen de otro usuario (admins)
 * - Exportación CSV del historial personal
 *
 * Capabilities:
 *   aura_finance_view_user_summary   — Ver propio dashboard personal
 *   aura_finance_view_others_summary — Ver dashboard de otros usuarios
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Financial_User_Dashboard {

    // -------------------------------------------------------------------------
    // Inicialización
    // -------------------------------------------------------------------------

    public static function init(): void {
        // Widget en el Panel de WordPress
        add_action( 'wp_dashboard_setup', [ __CLASS__, 'register_wp_widget' ] );

        // AJAX: cargar resumen de cualquier usuario (admin)
        add_action( 'wp_ajax_aura_get_user_financial_summary', [ __CLASS__, 'ajax_get_summary' ] );

        // AJAX: exportar CSV del historial personal
        add_action( 'wp_ajax_aura_export_personal_finance_csv', [ __CLASS__, 'ajax_export_csv' ] );
    }

    // -------------------------------------------------------------------------
    // Widget de WordPress Dashboard
    // -------------------------------------------------------------------------

    public static function register_wp_widget(): void {
        if (
            ! current_user_can( 'aura_finance_view_user_summary' ) &&
            ! current_user_can( 'aura_finance_view_all' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            return;
        }

        wp_add_dashboard_widget(
            'aura_personal_finance_widget',
            __( '💰 Mi Resumen Financiero — AURA', 'aura-suite' ),
            [ __CLASS__, 'render_wp_widget' ]
        );
    }

    public static function render_wp_widget(): void {
        $user_id = get_current_user_id();
        $summary = self::get_user_financial_summary( $user_id );

        if ( is_wp_error( $summary ) ) {
            echo '<p class="description">' . esc_html( $summary->get_error_message() ) . '</p>';
            return;
        }

        $income   = 0.0;
        $expense  = 0.0;
        $pending  = 0;

        foreach ( $summary['totals'] as $row ) {
            // Perspectiva usuario: egreso org → usuario = ingreso usuario
            if ( $row->transaction_type === 'expense' ) {
                $income = (float) $row->total;
            } elseif ( $row->transaction_type === 'income' ) {
                $expense = (float) $row->total;
            }
        }
        $balance = $income - $expense;

        // Contar pendientes (status = 'pending') donde el usuario está vinculado
        $pending = self::count_pending_for_user( $user_id );

        $currency = get_option( 'aura_currency_symbol', '$' );
        ?>
        <div class="aura-user-widget">
            <div class="aura-widget-stats">
                <div class="aura-widget-stat aura-stat-income">
                    <span class="aura-stat-label"><?php _e( 'Cobros', 'aura-suite' ); ?></span>
                    <span class="aura-stat-value"><?php echo esc_html( $currency . number_format( $income, 2, '.', ',' ) ); ?></span>
                </div>
                <div class="aura-widget-stat aura-stat-expense">
                    <span class="aura-stat-label"><?php _e( 'Pagos', 'aura-suite' ); ?></span>
                    <span class="aura-stat-value"><?php echo esc_html( $currency . number_format( $expense, 2, '.', ',' ) ); ?></span>
                </div>
                <div class="aura-widget-stat aura-stat-balance <?php echo $balance >= 0 ? 'positive' : 'negative'; ?>">
                    <span class="aura-stat-label"><?php _e( 'Saldo Neto', 'aura-suite' ); ?></span>
                    <span class="aura-stat-value"><?php echo esc_html( ( $balance >= 0 ? '+' : '' ) . $currency . number_format( abs( $balance ), 2, '.', ',' ) ); ?></span>
                </div>
                <?php if ( $pending > 0 ) : ?>
                <div class="aura-widget-stat aura-stat-pending">
                    <span class="aura-stat-label"><?php _e( 'Pendientes', 'aura-suite' ); ?></span>
                    <span class="aura-stat-value"><?php echo esc_html( $pending ); ?> ⏳</span>
                </div>
                <?php endif; ?>
            </div>

            <?php
            // Últimos 5 movimientos
            $recent = self::get_recent_movements( $user_id, 5 );
            if ( ! empty( $recent ) ) :
            ?>
            <div class="aura-widget-movements">
                <p class="aura-widget-subtitle"><?php _e( 'Últimos movimientos', 'aura-suite' ); ?></p>
                <ul class="aura-movements-list">
                    <?php foreach ( $recent as $mov ) :
                        $is_income = ( $mov->transaction_type === 'expense' ); // Perspectiva usuario: egreso org = ingreso usuario
                        $status_icon = $mov->status === 'approved' ? '✅' : ( $mov->status === 'pending' ? '⏳' : '❌' );
                        $date_fmt    = date_i18n( 'd M', strtotime( $mov->transaction_date ) );
                    ?>
                    <li class="aura-movement-item">
                        <span class="aura-mov-date"><?php echo esc_html( $date_fmt ); ?></span>
                        <span class="aura-mov-desc"><?php echo esc_html( mb_strimwidth( $mov->description, 0, 28, '…' ) ); ?></span>
                        <span class="aura-mov-type <?php echo $is_income ? 'income' : 'expense'; ?>">
                            <?php echo $is_income ? '↑' : '↓'; ?>
                            <?php echo esc_html( get_option( 'aura_currency_symbol', '$' ) . number_format( (float) $mov->amount, 2, '.', ',' ) ); ?>
                        </span>
                        <span class="aura-mov-status" title="<?php echo esc_attr( $mov->status ); ?>"><?php echo $status_icon; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div style="margin-top:10px;text-align:right;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-my-finance' ) ); ?>" class="button button-small">
                    <?php _e( 'Ver mi dashboard completo →', 'aura-suite' ); ?>
                </a>
            </div>
        </div>
        <style>
        .aura-user-widget{font-size:13px;}
        .aura-widget-stats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;}
        .aura-widget-stat{flex:1;min-width:80px;background:#f6f7f7;border-radius:6px;padding:8px 10px;text-align:center;border-left:3px solid #ccc;}
        .aura-stat-income{border-color:#00a32a;}
        .aura-stat-expense{border-color:#d63638;}
        .aura-stat-balance.positive{border-color:#0073aa;}
        .aura-stat-balance.negative{border-color:#d63638;}
        .aura-stat-pending{border-color:#f0a000;}
        .aura-stat-label{display:block;font-size:10px;color:#8c8f94;text-transform:uppercase;letter-spacing:.5px;}
        .aura-stat-value{display:block;font-size:15px;font-weight:700;margin-top:3px;}
        .aura-stat-income .aura-stat-value{color:#00a32a;}
        .aura-stat-expense .aura-stat-value{color:#d63638;}
        .aura-stat-balance.positive .aura-stat-value{color:#0073aa;}
        .aura-widget-subtitle{margin:0 0 6px;font-weight:600;color:#3c434a;font-size:12px;}
        .aura-movements-list{margin:0;padding:0;list-style:none;}
        .aura-movement-item{display:flex;align-items:center;gap:6px;padding:4px 0;border-bottom:1px solid #f0f0f1;font-size:12px;}
        .aura-movement-item:last-child{border-bottom:none;}
        .aura-mov-date{color:#8c8f94;min-width:38px;}
        .aura-mov-desc{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .aura-mov-type.income{color:#00a32a;font-weight:600;min-width:80px;text-align:right;}
        .aura-mov-type.expense{color:#d63638;font-weight:600;min-width:80px;text-align:right;}
        .aura-mov-status{min-width:18px;text-align:center;}
        </style>
        <?php
    }

    // -------------------------------------------------------------------------
    // Renderizado de la página dedicada
    // -------------------------------------------------------------------------

    public static function render(): void {
        // Verificar acceso
        if (
            ! current_user_can( 'aura_finance_view_user_summary' ) &&
            ! current_user_can( 'aura_finance_view_all' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'No tienes permisos para ver esta página.', 'aura-suite' ) );
        }

        include AURA_PLUGIN_DIR . 'templates/financial/user-dashboard.php';
    }

    // -------------------------------------------------------------------------
    // Lógica de datos
    // -------------------------------------------------------------------------

    /**
     * Obtener resumen financiero de un usuario (ingresos / egresos agrupados).
     *
     * @param int $user_id
     * @return array|WP_Error
     */
    public static function get_user_financial_summary( int $user_id ): array|\WP_Error {
        $current_user = get_current_user_id();

        // Control de acceso
        if ( $user_id !== $current_user ) {
            if (
                ! current_user_can( 'aura_finance_view_others_summary' ) &&
                ! current_user_can( 'manage_options' )
            ) {
                return new \WP_Error( 'forbidden', __( 'No tienes permiso para ver el resumen de otro usuario.', 'aura-suite' ) );
            }
        } else {
            if (
                ! current_user_can( 'aura_finance_view_user_summary' ) &&
                ! current_user_can( 'aura_finance_view_all' ) &&
                ! current_user_can( 'manage_options' )
            ) {
                return new \WP_Error( 'forbidden', __( 'No tienes permiso para ver resúmenes financieros.', 'aura-suite' ) );
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        // Totales aprobados por tipo
        $totals = $wpdb->get_results( $wpdb->prepare(
            "SELECT transaction_type, SUM(amount) as total, COUNT(*) as count
             FROM {$table}
             WHERE related_user_id = %d
               AND status = 'approved'
               AND deleted_at IS NULL
             GROUP BY transaction_type",
            $user_id
        ) );

        return [
            'totals'  => $totals ?? [],
            'user_id' => $user_id,
        ];
    }

    /**
     * Contar movimientos pendientes de aprobación vinculados al usuario.
     */
    public static function count_pending_for_user( int $user_id ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE related_user_id = %d AND status = 'pending' AND deleted_at IS NULL",
            $user_id
        ) );

        return (int) $count;
    }

    /**
     * Obtener últimos N movimientos vinculados al usuario.
     *
     * @param int $user_id
     * @param int $limit
     * @param array $filters  date_from, date_to, concept, status, paged
     */
    public static function get_recent_movements( int $user_id, int $limit = 10, array $filters = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $where  = $wpdb->prepare( 'related_user_id = %d AND deleted_at IS NULL', $user_id );
        $params = [];

        if ( ! empty( $filters['date_from'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date >= %s', $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date <= %s', $filters['date_to'] );
        }
        if ( ! empty( $filters['concept'] ) ) {
            $where .= $wpdb->prepare( ' AND related_user_concept = %s', $filters['concept'] );
        }
        if ( ! empty( $filters['status'] ) ) {
            $where .= $wpdb->prepare( ' AND status = %s', $filters['status'] );
        }

        // Paginación
        $per_page = 20;
        $paged    = max( 1, (int) ( $filters['paged'] ?? 1 ) );
        $offset   = ( $paged - 1 ) * $per_page;
        $data_limit = $limit > 0 ? $limit : $per_page;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            "SELECT id, transaction_type, amount, description, transaction_date,
                    status, related_user_concept, created_by
             FROM {$table}
             WHERE {$where}
             ORDER BY transaction_date DESC, id DESC
             LIMIT {$data_limit} OFFSET {$offset}"
        ) ?: [];
    }

    /**
     * Contar total de movimientos para paginación.
     */
    public static function count_movements( int $user_id, array $filters = [] ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $where = $wpdb->prepare( 'related_user_id = %d AND deleted_at IS NULL', $user_id );

        if ( ! empty( $filters['date_from'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date >= %s', $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date <= %s', $filters['date_to'] );
        }
        if ( ! empty( $filters['concept'] ) ) {
            $where .= $wpdb->prepare( ' AND related_user_concept = %s', $filters['concept'] );
        }
        if ( ! empty( $filters['status'] ) ) {
            $where .= $wpdb->prepare( ' AND status = %s', $filters['status'] );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
    }

    /**
     * Obtener equipos a cargo del usuario (tabla inventory_loans si existe).
     */
    public static function get_inventory_loans( int $user_id ): array {
        global $wpdb;
        $loans_table = $wpdb->prefix . 'aura_inventory_loans';

        // Verificar que la tabla exista para evitar errores si el módulo no está instalado
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
            DB_NAME,
            $loans_table
        ) );

        if ( ! $table_exists ) {
            return [];
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT l.id, l.loan_date, l.expected_return_date, l.returned_at,
                    COALESCE(i.name, l.item_name, 'Equipo') AS item_name
             FROM {$loans_table} l
             LEFT JOIN {$wpdb->prefix}aura_inventory_items i ON l.item_id = i.id
             WHERE l.loaned_to_user_id = %d AND l.returned_at IS NULL
             ORDER BY l.expected_return_date ASC",
            $user_id
        ) ) ?: [];
    }

    // -------------------------------------------------------------------------
    // AJAX
    // -------------------------------------------------------------------------

    /**
     * AJAX: obtener resumen de un usuario (para selector admin).
     */
    public static function ajax_get_summary(): void {
        check_ajax_referer( 'aura_user_dashboard_nonce', 'nonce' );

        $target_user_id = intval( $_POST['user_id'] ?? get_current_user_id() );

        // El usuario puede pedir su propio resumen o, si tiene permiso, el de otro
        if (
            $target_user_id !== get_current_user_id() &&
            ! current_user_can( 'aura_finance_view_others_summary' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $filters = [
            'date_from' => sanitize_text_field( $_POST['date_from'] ?? '' ),
            'date_to'   => sanitize_text_field( $_POST['date_to']   ?? '' ),
            'concept'   => sanitize_key( $_POST['concept']           ?? '' ),
            'status'    => sanitize_key( $_POST['status']            ?? '' ),
            'paged'     => intval( $_POST['paged'] ?? 1 ),
        ];

        $summary   = self::get_user_financial_summary( $target_user_id );
        if ( is_wp_error( $summary ) ) {
            wp_send_json_error( [ 'message' => $summary->get_error_message() ] );
        }

        $movements = self::get_recent_movements( $target_user_id, 0, $filters );
        $total     = self::count_movements( $target_user_id, $filters );
        $loans     = self::get_inventory_loans( $target_user_id );
        $pending   = self::count_pending_for_user( $target_user_id );
        $user_data = get_userdata( $target_user_id );

        wp_send_json_success( [
            'summary'   => $summary['totals'],
            'movements' => $movements,
            'total'     => $total,
            'loans'     => $loans,
            'pending'   => $pending,
            'user'      => $user_data ? [
                'id'         => $user_data->ID,
                'name'       => $user_data->display_name,
                'email'      => $user_data->user_email,
                'avatar_url' => get_avatar_url( $user_data->ID, [ 'size' => 40 ] ),
            ] : null,
        ] );
    }

    /**
     * AJAX: exportar CSV del historial personal.
     */
    public static function ajax_export_csv(): void {
        check_ajax_referer( 'aura_user_dashboard_nonce', 'nonce' );

        $target_user_id = intval( $_POST['user_id'] ?? get_current_user_id() );

        if (
            $target_user_id !== get_current_user_id() &&
            ! current_user_can( 'aura_finance_view_others_summary' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'Sin permisos.', 'aura-suite' ) );
        }

        $filters = [
            'date_from' => sanitize_text_field( $_POST['date_from'] ?? '' ),
            'date_to'   => sanitize_text_field( $_POST['date_to']   ?? '' ),
            'concept'   => sanitize_key( $_POST['concept']           ?? '' ),
            'status'    => sanitize_key( $_POST['status']            ?? '' ),
        ];

        $movements = self::get_recent_movements( $target_user_id, -1, $filters );
        $user_data = get_userdata( $target_user_id );
        $currency  = get_option( 'aura_currency_symbol', '$' );

        $concepts = self::get_concepts_labels();

        $filename = 'resumen-financiero-' . sanitize_file_name( $user_data ? $user_data->user_login : $target_user_id ) . '-' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        echo "\xEF\xBB\xBF"; // BOM UTF-8

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'Fecha', 'Descripción', 'Tipo', 'Concepto', 'Monto', 'Estado' ], ';' );

        foreach ( $movements as $mov ) {
            fputcsv( $out, [
                $mov->transaction_date,
                $mov->description,
                $mov->transaction_type === 'expense' ? 'Ingreso' : 'Egreso', // Perspectiva usuario
                $concepts[ $mov->related_user_concept ] ?? $mov->related_user_concept,
                number_format( (float) $mov->amount, 2, '.', '' ),
                ucfirst( $mov->status ),
            ], ';' );
        }

        fclose( $out );
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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
