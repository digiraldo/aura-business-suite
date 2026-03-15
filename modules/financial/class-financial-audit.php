<?php
/**
 * Sistema de Auditoría y Trazabilidad — Fase 5, Item 5.3
 *
 * Registra todas las acciones críticas del módulo financiero en
 * wp_aura_finance_audit_log. Usa los do_action ya existentes en
 * las clases de transacciones para no modificar esos archivos.
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Financial_Audit {

    const TABLE = 'aura_finance_audit_log';

    /* ============================================================
     * ACCIONES VÁLIDAS
     * ============================================================ */
    const ACTIONS = [
        // Transacciones
        'transaction_created',
        'transaction_updated',
        'transaction_deleted',
        'transaction_restored',
        'transaction_permanently_deleted',
        'transaction_approved',
        'transaction_rejected',
        // Categorías
        'category_created',
        'category_updated',
        'category_deleted',
        // Presupuestos
        'budget_created',
        'budget_updated',
        'budget_deleted',
        'budget_exceeded',
        // Operaciones masivas
        'export_executed',
        'import_executed',
        // Config
        'settings_updated',
    ];

    /* ============================================================
     * Init
     * ============================================================ */

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'maybe_create_table' ] );

        // ── Hooks de transacciones (ya existen en las clases) ──
        add_action( 'aura_finance_transaction_created',  [ __CLASS__, 'on_transaction_created' ],  10, 3 );
        add_action( 'aura_finance_transaction_updated',  [ __CLASS__, 'on_transaction_updated' ],  10, 4 );
        add_action( 'aura_finance_transaction_approved', [ __CLASS__, 'on_transaction_approved' ], 10, 3 );
        add_action( 'aura_finance_transaction_rejected', [ __CLASS__, 'on_transaction_rejected' ], 10, 3 );
        add_action( 'aura_finance_transaction_trashed',  [ __CLASS__, 'on_transaction_trashed' ],  10, 2 );
        add_action( 'aura_finance_transaction_restored', [ __CLASS__, 'on_transaction_restored' ], 10, 2 );

        // ── Hooks propios para presupuestos, categorías, export, import ──
        add_action( 'aura_finance_budget_saved',    [ __CLASS__, 'on_budget_saved' ],    10, 3 );
        add_action( 'aura_finance_budget_deleted',  [ __CLASS__, 'on_budget_deleted' ],  10, 1 );
        add_action( 'aura_finance_budget_exceeded', [ __CLASS__, 'on_budget_exceeded' ], 10, 2 );
        add_action( 'aura_finance_category_saved',  [ __CLASS__, 'on_category_saved' ],  10, 3 );
        add_action( 'aura_finance_category_deleted',[ __CLASS__, 'on_category_deleted' ], 10, 1 );
        add_action( 'aura_finance_export_executed', [ __CLASS__, 'on_export_executed' ], 10, 3 );
        add_action( 'aura_finance_import_executed', [ __CLASS__, 'on_import_executed' ], 10, 3 );

        // ── AJAX propios ──
        add_action( 'wp_ajax_aura_audit_get_logs',    [ __CLASS__, 'ajax_get_logs' ] );
        add_action( 'wp_ajax_aura_audit_export_csv',  [ __CLASS__, 'ajax_export_csv' ] );
        add_action( 'wp_ajax_aura_audit_purge',       [ __CLASS__, 'ajax_purge' ] );
        add_action( 'wp_ajax_aura_audit_recent',      [ __CLASS__, 'ajax_recent_activity' ] );

        // Cron de limpieza automática
        add_action( 'aura_finance_audit_cleanup', [ __CLASS__, 'cron_cleanup' ] );
        if ( ! wp_next_scheduled( 'aura_finance_audit_cleanup' ) ) {
            wp_schedule_event( time(), 'weekly', 'aura_finance_audit_cleanup' );
        }

        // ── FASE D: Alertas de seguridad ──
        add_action( 'aura_finance_security_alert', [ __CLASS__, 'notify_security_alert' ], 10, 2 );
        add_action( 'aura_finance_security_check', [ __CLASS__, 'run_security_checks' ] );
        if ( ! wp_next_scheduled( 'aura_finance_security_check' ) ) {
            wp_schedule_event( time(), 'hourly', 'aura_finance_security_check' );
        }
    }

    /* ============================================================
     * Crear tabla de auditoría
     * ============================================================ */

    public static function maybe_create_table() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) return;

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            action VARCHAR(60) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id BIGINT UNSIGNED NULL,
            old_value LONGTEXT NULL,
            new_value LONGTEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(500) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user    (user_id),
            INDEX idx_action  (action),
            INDEX idx_entity  (entity_type, entity_id),
            INDEX idx_date    (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /* ============================================================
     * Función central de logging
     * ============================================================ */

    public static function log_action(
        string $action,
        string $entity_type,
        $entity_id  = null,
        $old_value  = null,
        $new_value  = null
    ) {
        global $wpdb;

        if ( ! in_array( $action, self::ACTIONS, true ) ) return;

        $wpdb->insert(
            $wpdb->prefix . self::TABLE,
            [
                'user_id'     => get_current_user_id(),
                'action'      => $action,
                'entity_type' => $entity_type,
                'entity_id'   => $entity_id,
                'old_value'   => $old_value !== null ? wp_json_encode( $old_value, JSON_UNESCAPED_UNICODE ) : null,
                'new_value'   => $new_value !== null ? wp_json_encode( $new_value, JSON_UNESCAPED_UNICODE ) : null,
                'ip_address'  => self::get_user_ip(),
                'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] )
                                 ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 )
                                 : null,
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    /* ============================================================
     * Helper: obtener IP real del usuario
     * ============================================================ */

    private static function get_user_ip(): string {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];
        foreach ( $keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = filter_var(
                    strtok( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ), ',' ),
                    FILTER_VALIDATE_IP
                );
                if ( $ip ) return $ip;
            }
        }
        return '0.0.0.0';
    }

    /* ============================================================
     * Callbacks para hooks existentes de TRANSACCIONES
     * ============================================================ */

    public static function on_transaction_created( $transaction_id, $transaction_type, $amount ) {
        self::log_action(
            'transaction_created',
            'transaction',
            $transaction_id,
            null,
            [ 'type' => $transaction_type, 'amount' => $amount ]
        );
    }

    public static function on_transaction_updated( $transaction_id, $old_data, $new_data, $user_id ) {
        // Construir diff solo de campos que cambiaron
        $diff_old = [];
        $diff_new = [];
        $watch = [ 'amount', 'category_id', 'transaction_date', 'description',
                   'payment_method', 'status', 'tags', 'notes' ];
        foreach ( $watch as $field ) {
            if ( isset( $new_data[ $field ] ) && (string) $old_data[ $field ] !== (string) $new_data[ $field ] ) {
                $diff_old[ $field ] = $old_data[ $field ];
                $diff_new[ $field ] = $new_data[ $field ];
            }
        }
        self::log_action( 'transaction_updated', 'transaction', $transaction_id, $diff_old ?: null, $diff_new ?: null );
    }

    public static function on_transaction_approved( $transaction_id, $user_id, $note ) {
        self::log_action(
            'transaction_approved', 'transaction', $transaction_id,
            null, [ 'approved_by' => $user_id, 'note' => $note ]
        );
    }

    public static function on_transaction_rejected( $transaction_id, $user_id, $reason ) {
        self::log_action(
            'transaction_rejected', 'transaction', $transaction_id,
            null, [ 'rejected_by' => $user_id, 'reason' => $reason ]
        );
    }

    public static function on_transaction_trashed( $transaction_id, $transaction ) {
        self::log_action(
            'transaction_deleted', 'transaction', $transaction_id,
            is_array( $transaction )
                ? array_intersect_key( $transaction, array_flip( [ 'description', 'amount', 'transaction_date' ] ) )
                : null,
            null
        );
    }

    public static function on_transaction_restored( $transaction_id, $transaction ) {
        self::log_action(
            'transaction_restored', 'transaction', $transaction_id,
            null,
            is_array( $transaction )
                ? array_intersect_key( $transaction, array_flip( [ 'description', 'amount', 'transaction_date' ] ) )
                : null
        );
    }

    /* ============================================================
     * Callbacks para PRESUPUESTOS
     * ============================================================ */

    public static function on_budget_saved( $budget_id, $action, $data ) {
        $audit_action = ( $action === 'created' ) ? 'budget_created' : 'budget_updated';
        self::log_action( $audit_action, 'budget', $budget_id, null, $data );
    }

    public static function on_budget_deleted( $budget_id ) {
        self::log_action( 'budget_deleted', 'budget', $budget_id );
    }

    public static function on_budget_exceeded( $budget_id, $data ) {
        self::log_action( 'budget_exceeded', 'budget', $budget_id, null, $data );
    }

    /* ============================================================
     * Callbacks para CATEGORÍAS
     * ============================================================ */

    public static function on_category_saved( $category_id, $action, $data ) {
        $audit_action = ( $action === 'created' ) ? 'category_created' : 'category_updated';
        self::log_action( $audit_action, 'category', $category_id, null, $data );
    }

    public static function on_category_deleted( $category_id ) {
        self::log_action( 'category_deleted', 'category', $category_id );
    }

    /* ============================================================
     * Callbacks para EXPORT / IMPORT
     * ============================================================ */

    public static function on_export_executed( $format, $count, $filters ) {
        self::log_action(
            'export_executed', 'export', null,
            null,
            [ 'format' => $format, 'record_count' => $count, 'filters' => $filters ]
        );
    }

    public static function on_import_executed( $total, $successful, $errors ) {
        self::log_action(
            'import_executed', 'import', null,
            null,
            [ 'total' => $total, 'successful' => $successful, 'errors' => $errors ]
        );
    }

    /* ============================================================
     * AJAX: Obtener logs paginados con filtros
     * ============================================================ */

    public static function ajax_get_logs() {
        check_ajax_referer( 'aura_audit_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_finance_view_all' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $page     = max( 1, absint( $_POST['page']    ?? 1 ) );
        $per_page = 25;
        $offset   = ( $page - 1 ) * $per_page;

        $where  = [ '1=1' ];
        $params = [];

        $user_id     = absint( $_POST['filter_user']   ?? 0 );
        $action      = sanitize_key( $_POST['filter_action'] ?? '' );
        $entity_type = sanitize_key( $_POST['filter_entity'] ?? '' );
        $date_from   = sanitize_text_field( $_POST['filter_date_from'] ?? '' );
        $date_to     = sanitize_text_field( $_POST['filter_date_to']   ?? '' );
        $ip          = sanitize_text_field( $_POST['filter_ip']        ?? '' );

        if ( $user_id )     { $where[] = 'l.user_id = %d';         $params[] = $user_id; }
        if ( $action )      { $where[] = 'l.action = %s';          $params[] = $action; }
        if ( $entity_type ) { $where[] = 'l.entity_type = %s';     $params[] = $entity_type; }
        if ( $date_from )   { $where[] = 'l.created_at >= %s';     $params[] = $date_from . ' 00:00:00'; }
        if ( $date_to )     { $where[] = 'l.created_at <= %s';     $params[] = $date_to   . ' 23:59:59'; }
        if ( $ip )          { $where[] = 'l.ip_address LIKE %s';   $params[] = '%' . $wpdb->esc_like( $ip ) . '%'; }

        $where_sql = implode( ' AND ', $where );

        $count_sql = "SELECT COUNT(*) FROM `{$table}` l WHERE {$where_sql}";
        $total     = (int) ( empty( $params )
            ? $wpdb->get_var( $count_sql )
            : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

        $data_sql = "SELECT l.*, u.display_name AS user_name
                     FROM `{$table}` l
                     LEFT JOIN `{$wpdb->users}` u ON u.ID = l.user_id
                     WHERE {$where_sql}
                     ORDER BY l.created_at DESC
                     LIMIT {$per_page} OFFSET {$offset}";
        $logs = empty( $params )
            ? $wpdb->get_results( $data_sql )
            : $wpdb->get_results( $wpdb->prepare( $data_sql, $params ) );

        // Parsear JSON en old/new_value
        foreach ( $logs as &$log ) {
            $log->old_value = $log->old_value ? json_decode( $log->old_value, true ) : null;
            $log->new_value = $log->new_value ? json_decode( $log->new_value, true ) : null;
        }
        unset( $log );

        wp_send_json_success( [
            'logs'     => $logs,
            'total'    => $total,
            'pages'    => (int) ceil( $total / $per_page ),
            'page'     => $page,
            'per_page' => $per_page,
        ] );
    }

    /* ============================================================
     * AJAX: Exportar logs a CSV
     * ============================================================ */

    public static function ajax_export_csv() {
        check_ajax_referer( 'aura_audit_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Solo administradores pueden exportar logs.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $logs = $wpdb->get_results(
            "SELECT l.*, u.display_name AS user_name
             FROM `{$table}` l
             LEFT JOIN `{$wpdb->users}` u ON u.ID = l.user_id
             ORDER BY l.created_at DESC
             LIMIT 10000"
        );

        $rows   = [];
        $rows[] = implode( ',', [ 'ID', 'Fecha', 'Usuario', 'Acción', 'Entidad', 'ID Entidad', 'Valor Anterior', 'Valor Nuevo', 'IP', 'User Agent' ] );

        foreach ( $logs as $log ) {
            $rows[] = implode( ',', array_map( function ( $v ) {
                return '"' . str_replace( '"', '""', $v ?? '' ) . '"';
            }, [
                $log->id,
                $log->created_at,
                $log->user_name,
                $log->action,
                $log->entity_type,
                $log->entity_id,
                $log->old_value,
                $log->new_value,
                $log->ip_address,
                $log->user_agent,
            ] ) );
        }

        $filename = 'audit-log-' . date( 'Y-m-d' ) . '.csv';
        $content  = "\xEF\xBB\xBF" . implode( "\n", $rows ); // BOM UTF-8

        wp_send_json_success( [
            'filename' => $filename,
            'content'  => base64_encode( $content ),
        ] );
    }

    /* ============================================================
     * AJAX: Purgar logs antiguos manualmente
     * ============================================================ */

    public static function ajax_purge() {
        check_ajax_referer( 'aura_audit_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Solo administradores pueden purgar logs.', 'aura-suite' ) ], 403 );
        }

        $days = absint( $_POST['days'] ?? 365 );
        if ( $days < 30 ) $days = 30; // mínimo 30 días

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $count = $wpdb->query( $wpdb->prepare(
            "DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );

        wp_send_json_success( [
            'deleted' => $count,
            'message' => sprintf(
                __( '%d registros eliminados (más de %d días de antigüedad).', 'aura-suite' ),
                $count, $days
            ),
        ] );
    }

    /* ============================================================
     * AJAX: Actividad reciente (widget dashboard)
     * ============================================================ */

    public static function ajax_recent_activity() {
        check_ajax_referer( 'aura_audit_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_view_own' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        $my_only = ! empty( $_POST['my_only'] );
        wp_send_json_success( [ 'activity' => self::get_recent_activity( 10, $my_only ) ] );
    }

    /* ============================================================
     * Obtener actividad reciente (uso en dashboard)
     * ============================================================ */

    public static function get_recent_activity( int $limit = 10, bool $current_user_only = false ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $where  = '1=1';
        $params = [];

        if ( $current_user_only ) {
            $where   .= ' AND l.user_id = %d';
            $params[] = get_current_user_id();
        }

        $sql = "SELECT l.*, u.display_name AS user_name
                FROM `{$table}` l
                LEFT JOIN `{$wpdb->users}` u ON u.ID = l.user_id
                WHERE {$where}
                ORDER BY l.created_at DESC
                LIMIT %d";
        $params[] = $limit;

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

        // Agregar tiempo relativo
        foreach ( $results as &$row ) {
            $row->time_ago = self::time_ago( $row->created_at );
        }
        unset( $row );

        return $results;
    }

    /* ============================================================
     * Helper: tiempo relativo
     * ============================================================ */

    private static function time_ago( string $datetime ): string {
        $diff = time() - strtotime( $datetime );
        if ( $diff < 60 )    return __( 'hace un momento', 'aura-suite' );
        if ( $diff < 3600 )  return sprintf( __( 'hace %d minutos', 'aura-suite' ), (int) ( $diff / 60 ) );
        if ( $diff < 86400 ) return sprintf( __( 'hace %d horas', 'aura-suite' ), (int) ( $diff / 3600 ) );
        if ( $diff < 604800 )return sprintf( __( 'hace %d días', 'aura-suite' ), (int) ( $diff / 86400 ) );
        return date_i18n( get_option( 'date_format' ), strtotime( $datetime ) );
    }

    /* ============================================================
     * Cron: limpiar logs según retención configurada
     * ============================================================ */

    public static function cron_cleanup() {
        $days = (int) get_option( 'aura_audit_log_retention_days', 365 );
        if ( $days < 30 ) $days = 30;

        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM `{$wpdb->prefix}" . self::TABLE . "` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
    }

    /* ============================================================
     * Render página de auditoría
     * ============================================================ */

    public static function render() {
        include AURA_PLUGIN_DIR . 'templates/financial/audit-log-page.php';
    }

    /* ============================================================
     * Widget de Actividad Reciente para el dashboard
     * ============================================================ */

    public static function render_dashboard_widget() {
        $activities = self::get_recent_activity( 10, false );
        $audit_url  = admin_url( 'admin.php?page=aura-financial-audit' );

        $action_labels = [
            'transaction_created'            => __( 'Transacción creada',          'aura-suite' ),
            'transaction_updated'            => __( 'Transacción editada',         'aura-suite' ),
            'transaction_deleted'            => __( 'Transacción eliminada',       'aura-suite' ),
            'transaction_restored'           => __( 'Transacción restaurada',      'aura-suite' ),
            'transaction_permanently_deleted'=> __( 'Eliminación permanente',      'aura-suite' ),
            'transaction_approved'           => __( 'Transacción aprobada',        'aura-suite' ),
            'transaction_rejected'           => __( 'Transacción rechazada',       'aura-suite' ),
            'category_created'               => __( 'Categoría creada',            'aura-suite' ),
            'category_updated'               => __( 'Categoría editada',           'aura-suite' ),
            'category_deleted'               => __( 'Categoría eliminada',         'aura-suite' ),
            'budget_created'                 => __( 'Presupuesto creado',          'aura-suite' ),
            'budget_updated'                 => __( 'Presupuesto editado',         'aura-suite' ),
            'budget_deleted'                 => __( 'Presupuesto eliminado',       'aura-suite' ),
            'budget_exceeded'                => __( 'Presupuesto excedido',        'aura-suite' ),
            'export_executed'                => __( 'Exportación ejecutada',       'aura-suite' ),
            'import_executed'                => __( 'Importación ejecutada',       'aura-suite' ),
            'settings_updated'               => __( 'Configuración actualizada',   'aura-suite' ),
        ];

        $dot_colors = [
            'transaction_created'   => '#1e7e34',
            'transaction_updated'   => '#0056b3',
            'transaction_deleted'   => '#c00',
            'transaction_approved'  => '#00796b',
            'transaction_rejected'  => '#e65100',
            'transaction_restored'  => '#1e7e34',
            'category_created'      => '#6a1b9a',
            'category_updated'      => '#6a1b9a',
            'category_deleted'      => '#c00',
            'budget_created'        => '#2e7d32',
            'budget_updated'        => '#0277bd',
            'budget_deleted'        => '#c00',
            'budget_exceeded'       => '#e65100',
            'export_executed'       => '#f57f17',
            'import_executed'       => '#4527a0',
            'settings_updated'      => '#546e7a',
        ];
        ?>
        <div class="aura-audit-widget" style="margin-top:20px;">
            <h3>
                <span class="dashicons dashicons-shield-alt" style="color:#2271b1;"></span>
                <?php esc_html_e( 'Actividad Reciente', 'aura-suite' ); ?>
                <a href="<?php echo esc_url( $audit_url ); ?>"
                   style="font-size:.78rem;font-weight:400;margin-left:8px;text-decoration:none;"
                ><?php esc_html_e( 'Ver registro completo →', 'aura-suite' ); ?></a>
            </h3>

            <?php if ( empty( $activities ) ) : ?>
                <p style="color:#888;font-size:.85rem;"><?php esc_html_e( 'Sin actividad registrada aún.', 'aura-suite' ); ?></p>
            <?php else : ?>
                <ul>
                    <?php foreach ( $activities as $act ) :
                        $color = $dot_colors[ $act->action ] ?? '#aaa';
                        $label = $action_labels[ $act->action ] ?? $act->action;
                    ?>
                    <li>
                        <span class="aura-activity-dot" style="background:<?php echo esc_attr( $color ); ?>;"></span>
                        <span>
                            <?php echo esc_html( $label ); ?>
                            <span class="audit-activity-meta">
                                <?php echo esc_html( $act->user_name ?? '—' ); ?>
                                &middot;
                                <?php echo esc_html( $act->time_ago ); ?>
                            </span>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ============================================================
     * FASE D — DETECCIÓN DE ANOMALÍAS Y ALERTAS DE SEGURIDAD
     * ============================================================ */

    /**
     * Punto de entrada del cron horario: ejecuta todas las comprobaciones.
     */
    public static function run_security_checks(): void {
        self::check_mass_deletions();
        self::check_unusual_activity_hours();
        self::check_mass_exports();
    }

    /**
     * Detecta eliminaciones masivas en la última hora.
     */
    private static function check_mass_deletions(): void {
        global $wpdb;
        $table     = $wpdb->prefix . self::TABLE;
        $threshold = (int) get_option( 'aura_audit_mass_delete_threshold', 10 );

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$table}`
             WHERE action = 'transaction_trashed'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        if ( $count >= $threshold ) {
            do_action( 'aura_finance_security_alert', 'mass_deletion', [
                'count'     => $count,
                'threshold' => $threshold,
                'periodo'   => '1 hora',
            ] );
        }
    }

    /**
     * Detecta actividad financiera fuera del horario habitual (por defecto 23:00–05:00).
     */
    private static function check_unusual_activity_hours(): void {
        global $wpdb;
        $table     = $wpdb->prefix . self::TABLE;
        $hour      = (int) current_time( 'G' ); // 0-23
        $night_min = (int) get_option( 'aura_audit_night_hour_start', 23 );
        $night_max = (int) get_option( 'aura_audit_night_hour_end', 5 );

        // Es horario nocturno si la hora >= night_min OR hora <= night_max
        $is_night = ( $hour >= $night_min || $hour <= $night_max );
        if ( ! $is_night ) return;

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$table}`
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );

        if ( $count > 5 ) {
            do_action( 'aura_finance_security_alert', 'unusual_hours', [
                'hora'  => $hour . ':00',
                'count' => $count,
            ] );
        }
    }

    /**
     * Detecta exportaciones masivas (múltiples en poco tiempo).
     */
    private static function check_mass_exports(): void {
        global $wpdb;
        $table     = $wpdb->prefix . self::TABLE;
        $threshold = (int) get_option( 'aura_audit_export_threshold', 5 );

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$table}`
             WHERE action = 'export_executed'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        if ( $count >= $threshold ) {
            do_action( 'aura_finance_security_alert', 'mass_export', [
                'count'     => $count,
                'threshold' => $threshold,
                'periodo'   => '1 hora',
            ] );
        }
    }

    /**
     * Envía un correo de alerta al administrador y guarda la alerta en options
     * para que el dashboard pueda mostrarla.
     *
     * @param string $type  Tipo de alerta: mass_deletion, unusual_hours, mass_export...
     * @param array  $data  Datos de contexto.
     */
    public static function notify_security_alert( string $type, array $data ): void {
        // Evitar spam: no enviar la misma alerta más de una vez por hora
        $lock_key = 'aura_security_alert_sent_' . $type;
        if ( get_transient( $lock_key ) ) return;
        set_transient( $lock_key, 1, HOUR_IN_SECONDS );

        $labels = [
            'mass_deletion' => __( 'Eliminaciones masivas detectadas', 'aura-suite' ),
            'unusual_hours' => __( 'Actividad en horario inusual', 'aura-suite' ),
            'mass_export'   => __( 'Exportaciones masivas detectadas', 'aura-suite' ),
        ];

        $admin_email = get_option( 'admin_email' );
        $subject     = '[ALERTA] ' . aura_get_org_name() . ' — Aura Finanzas: ' . ( $labels[ $type ] ?? $type );

        $body  = __( 'Se ha detectado actividad sospechosa en el módulo financiero.', 'aura-suite' ) . "\n\n";
        foreach ( $data as $k => $v ) {
            $body .= ucfirst( $k ) . ': ' . $v . "\n";
        }
        $body .= "\n" . __( 'Revisa el log de auditoría:', 'aura-suite' ) . ' '
               . admin_url( 'admin.php?page=aura-audit-log' );

        wp_mail( $admin_email, $subject, $body );

        // Persistir la alerta para mostrarla en el dashboard (máx. 20)
        $alerts   = (array) get_option( 'aura_finance_security_alerts', [] );
        $alerts[] = [
            'type'    => $type,
            'label'   => $labels[ $type ] ?? $type,
            'data'    => $data,
            'time'    => current_time( 'mysql' ),
            'read'    => false,
        ];
        // Conservar solo las 20 más recientes
        $alerts = array_slice( array_reverse( $alerts ), 0, 20 );
        $alerts = array_reverse( $alerts );
        update_option( 'aura_finance_security_alerts', $alerts, false );
    }

}
