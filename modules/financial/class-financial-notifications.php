<?php
/**
 * Sistema de Notificaciones Financieras — Fase 5, Item 5.4
 *
 * Notificaciones in-app y por email para el módulo financiero.
 * Tabla propia: wp_aura_notifications
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Financial_Notifications {

    const TABLE          = 'aura_notifications';
    const NONCE          = 'aura_notifications_nonce';
    const PREFS_META_KEY = 'aura_notification_prefs';

    /* ============================================================
     * Tipos de notificación y sus labels
     * ============================================================ */
    public static function get_types(): array {
        return [
            // Transacciones
            'transaction_pending'  => __( 'Nueva transacción pendiente de aprobación', 'aura-suite' ),
            'transaction_approved' => __( 'Transacción aprobada',                      'aura-suite' ),
            'transaction_rejected' => __( 'Transacción rechazada',                     'aura-suite' ),
            'transaction_edited'   => __( 'Transacción editada por otro usuario',       'aura-suite' ),
            // Presupuestos
            'budget_warning'       => __( 'Presupuesto cercano al límite (80%)',        'aura-suite' ),
            'budget_exceeded'      => __( 'Presupuesto sobrepasado',                    'aura-suite' ),
            'budget_assigned'      => __( 'Nuevo presupuesto asignado',                 'aura-suite' ),
            // Recordatorios
            'reminder_pending'     => __( 'Transacciones pendientes de registro',       'aura-suite' ),
            'reminder_no_receipt'  => __( 'Transacciones sin comprobante',              'aura-suite' ),
            'reminder_rejected'    => __( 'Transacciones rechazadas sin acción',        'aura-suite' ),
            // Sistema
            'import_complete'      => __( 'Importación completada',                     'aura-suite' ),
            'export_ready'         => __( 'Exportación lista para descargar',           'aura-suite' ),
            'settings_updated'     => __( 'Configuración actualizada',                  'aura-suite' ),
        ];
    }

    /* ============================================================
     * Init
     * ============================================================ */

    public static function init(): void {
        // Crear tabla si no existe
        add_action( 'admin_init', [ __CLASS__, 'maybe_create_table' ] );

        // Admin bar — icono campana
        add_action( 'admin_bar_menu', [ __CLASS__, 'add_admin_bar_bell' ], 999 );

        // Hooks de eventos financieros existentes
        add_action( 'aura_finance_transaction_created',  [ __CLASS__, 'on_transaction_created'  ], 10, 3 );
        add_action( 'aura_finance_transaction_approved', [ __CLASS__, 'on_transaction_approved' ], 10, 3 );
        add_action( 'aura_finance_transaction_rejected', [ __CLASS__, 'on_transaction_rejected' ], 10, 3 );
        add_action( 'aura_finance_transaction_updated',  [ __CLASS__, 'on_transaction_updated'  ], 10, 4 );
        add_action( 'aura_finance_budget_exceeded',      [ __CLASS__, 'on_budget_exceeded'      ], 10, 2 );
        add_action( 'aura_finance_budget_saved',         [ __CLASS__, 'on_budget_saved'         ], 10, 3 );
        add_action( 'aura_finance_import_executed',      [ __CLASS__, 'on_import_executed'      ], 10, 3 );
        add_action( 'aura_finance_export_executed',      [ __CLASS__, 'on_export_executed'      ], 10, 3 );

        // AJAX handlers
        add_action( 'wp_ajax_aura_get_notifications',          [ __CLASS__, 'ajax_get_notifications'     ] );
        add_action( 'wp_ajax_aura_get_all_notifications',      [ __CLASS__, 'ajax_get_all_notifications' ] );
        add_action( 'wp_ajax_aura_mark_notification_read',     [ __CLASS__, 'ajax_mark_read'             ] );
        add_action( 'wp_ajax_aura_mark_all_notifications_read',[ __CLASS__, 'ajax_mark_all_read'         ] );
        add_action( 'wp_ajax_aura_delete_notification',        [ __CLASS__, 'ajax_delete'                ] );
        add_action( 'wp_ajax_aura_save_notification_prefs',    [ __CLASS__, 'ajax_save_prefs'            ] );

        // Cron
        add_action( 'aura_finance_daily_reminders',  [ __CLASS__, 'cron_daily_reminders'  ] );
        add_action( 'aura_finance_weekly_summary',   [ __CLASS__, 'cron_weekly_summary'   ] );

        self::schedule_crons();
    }

    /* ============================================================
     * Programar cron jobs
     * ============================================================ */

    private static function schedule_crons(): void {
        if ( ! wp_next_scheduled( 'aura_finance_daily_reminders' ) ) {
            // Diariamente a las 9:00 AM
            $next_9am = strtotime( 'today 09:00:00' );
            if ( $next_9am <= time() ) $next_9am = strtotime( 'tomorrow 09:00:00' );
            wp_schedule_event( $next_9am, 'daily', 'aura_finance_daily_reminders' );
        }
        if ( ! wp_next_scheduled( 'aura_finance_weekly_summary' ) ) {
            // Lunes próximo a las 9:00 AM
            $next_monday = strtotime( 'next monday 09:00:00' );
            wp_schedule_event( $next_monday, 'weekly', 'aura_finance_weekly_summary' );
        }
    }

    /* ============================================================
     * Crear tabla
     * ============================================================ */

    public static function maybe_create_table(): void {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'info',
            title VARCHAR(255) NOT NULL DEFAULT '',
            message TEXT NOT NULL,
            link VARCHAR(500) NOT NULL DEFAULT '',
            is_read TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_read (user_id, is_read),
            KEY idx_created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /* ============================================================
     * Función central para crear una notificación
     * ============================================================ */

    /**
     * Enviar notificación in-app (y opcionalmente email) a un usuario.
     *
     * @param int    $user_id  ID del usuario destino
     * @param string $type     Clave del tipo (ver get_types())
     * @param string $title    Título corto
     * @param string $message  Cuerpo del mensaje
     * @param string $link     URL de acción (vacío si no aplica)
     * @param bool   $send_email Si debe intentar enviar email según prefs
     */
    public static function notify(
        int    $user_id,
        string $type,
        string $title,
        string $message,
        string $link       = '',
        bool   $send_email = true
    ): int {
        global $wpdb;

        if ( ! $user_id || ! get_userdata( $user_id ) ) return 0;

        // Respetar "no molestar"
        if ( self::is_do_not_disturb( $user_id ) && $send_email ) {
            $send_email = false;
        }

        $id = (int) $wpdb->insert(
            $wpdb->prefix . self::TABLE,
            [
                'user_id'    => $user_id,
                'type'       => $type,
                'title'      => $title,
                'message'    => $message,
                'link'       => $link,
                'is_read'    => 0,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%d', '%s' ]
        );

        // Email según prefs
        if ( $send_email && self::user_wants_email( $user_id, $type ) ) {
            $freq = self::get_pref( $user_id, 'email_frequency', 'immediate' );
            if ( $freq === 'immediate' ) {
                self::send_email( $user_id, $title, $message, $link );
            }
            // daily/weekly se acumula en la tabla y se envía por cron
        }

        return $id;
    }

    /* ============================================================
     * Notificar a todos los aprobadores
     * ============================================================ */

    private static function notify_approvers(
        string $type,
        string $title,
        string $message,
        string $link = ''
    ): void {
        $approvers = get_users( [ 'capability' => 'aura_finance_approve' ] );
        // Fallback: admins
        if ( empty( $approvers ) ) {
            $approvers = get_users( [ 'role' => 'administrator' ] );
        }
        foreach ( $approvers as $u ) {
            self::notify( $u->ID, $type, $title, $message, $link );
        }
    }

    /* ============================================================
     * Handlers de eventos
     * ============================================================ */

    public static function on_transaction_created( int $tx_id, string $type, $amount ): void {
        global $wpdb;

        // Obtener descripción y categoría para mostrar en la notificación
        $t  = $wpdb->prefix . 'aura_finance_transactions';
        $ct = $wpdb->prefix . 'aura_finance_categories';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT t.description, COALESCE(c.name, '') AS category_name
             FROM {$t} t
             LEFT JOIN {$ct} c ON c.id = t.category_id
             WHERE t.id = %d",
            $tx_id
        ) );

        $type_label = $type === 'income' ? __( 'Ingreso', 'aura-suite' ) : __( 'Egreso', 'aura-suite' );
        $desc       = ( $row && ! empty( $row->description ) ) ? $row->description : '';
        $cat        = ( $row && ! empty( $row->category_name ) ) ? $row->category_name : '';

        $detail = '';
        if ( $cat )  $detail .= ' — ' . __( 'Categoría:', 'aura-suite' ) . ' ' . esc_html( $cat );
        if ( $desc ) $detail .= ' — ' . esc_html( $desc );

        $link  = admin_url( 'admin.php?page=aura-financial-pending' );
        $title = sprintf( __( 'Transacción #%d pendiente de aprobación', 'aura-suite' ), $tx_id );
        $msg   = sprintf(
            __( 'Transacción #%d (%s · $%s)%s está pendiente de aprobación.', 'aura-suite' ),
            $tx_id,
            $type_label,
            number_format( (float) $amount, 2, '.', ',' ),
            $detail
        );
        self::notify_approvers( 'transaction_pending', $title, $msg, $link );
    }

    public static function on_transaction_approved( int $tx_id, int $approver_id, string $note ): void {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT post_author FROM {$wpdb->posts} WHERE ID = %d", $tx_id
        ) );
        if ( ! $row ) return;

        $author_id    = (int) $row->post_author;
        $approver     = get_userdata( $approver_id );
        $approver_name = $approver ? $approver->display_name : __( 'Sistema', 'aura-suite' );
        $link         = admin_url( 'admin.php?page=aura-financial-transactions' );
        $title        = sprintf( __( 'Transacción #%d aprobada', 'aura-suite' ), $tx_id );
        $msg          = sprintf(
            __( 'Tu transacción #%d fue aprobada por %s.%s', 'aura-suite' ),
            $tx_id, $approver_name,
            $note ? ' ' . __( 'Nota:', 'aura-suite' ) . ' ' . $note : ''
        );
        self::notify( $author_id, 'transaction_approved', $title, $msg, $link );
    }

    public static function on_transaction_rejected( int $tx_id, int $approver_id, string $reason ): void {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT post_author FROM {$wpdb->posts} WHERE ID = %d", $tx_id
        ) );
        if ( ! $row ) return;

        $author_id    = (int) $row->post_author;
        $approver     = get_userdata( $approver_id );
        $approver_name = $approver ? $approver->display_name : __( 'Sistema', 'aura-suite' );
        $link         = admin_url( 'admin.php?page=aura-financial-transactions' );
        $title        = sprintf( __( 'Transacción #%d rechazada', 'aura-suite' ), $tx_id );
        $msg          = sprintf(
            __( 'Tu transacción #%d fue rechazada por %s. Motivo: %s', 'aura-suite' ),
            $tx_id, $approver_name, $reason ? $reason : __( 'sin motivo indicado', 'aura-suite' )
        );
        self::notify( $author_id, 'transaction_rejected', $title, $msg, $link );
    }

    /**
     * @param mixed $old_data
     * @param mixed $new_data
     */
    public static function on_transaction_updated( int $tx_id, $old_data, $new_data, int $editor_id ): void {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT post_author FROM {$wpdb->posts} WHERE ID = %d", $tx_id
        ) );
        if ( ! $row ) return;

        $author_id = (int) $row->post_author;
        // Solo notificar si editó alguien diferente al autor
        if ( $author_id === $editor_id ) return;

        $editor = get_userdata( $editor_id );
        $editor_name = $editor ? $editor->display_name : __( 'otro usuario', 'aura-suite' );
        $link  = admin_url( 'admin.php?page=aura-financial-transactions' );
        $title = sprintf( __( 'Transacción #%d fue editada', 'aura-suite' ), $tx_id );
        $msg   = sprintf(
            __( 'Tu transacción #%d fue modificada por %s.', 'aura-suite' ),
            $tx_id, $editor_name
        );
        self::notify( $author_id, 'transaction_edited', $title, $msg, $link );
    }

    public static function on_budget_exceeded( int $budget_id, array $data ): void {
        $category = isset( $data['category_name'] ) ? $data['category_name'] : "#$budget_id";
        $pct      = isset( $data['percentage'] ) ? round( $data['percentage'], 1 ) : '100+';
        $title    = sprintf( __( 'Presupuesto "%s" sobrepasado (%s%%)', 'aura-suite' ), $category, $pct );
        $msg      = sprintf(
            __( 'El presupuesto de la categoría "%s" ha sido sobrepasado (%s%% ejecutado).', 'aura-suite' ),
            $category, $pct
        );
        $link = admin_url( 'admin.php?page=aura-financial-budgets' );

        // Notificar admins y gestores de finanzas
        $recipients = array_merge(
            get_users( [ 'role' => 'administrator' ] ),
            get_users( [ 'capability' => 'aura_finance_view_all' ] )
        );
        $seen = [];
        foreach ( $recipients as $u ) {
            if ( isset( $seen[ $u->ID ] ) ) continue;
            $seen[ $u->ID ] = true;
            self::notify( $u->ID, 'budget_exceeded', $title, $msg, $link );
        }
    }

    public static function on_budget_saved( int $budget_id, string $action, array $data ): void {
        if ( $action !== 'created' ) return;
        global $wpdb;

        // Obtener nombre del área y de la categoría para mostrar en la notificación
        $area_id     = isset( $data['area_id'] )     ? (int) $data['area_id']     : 0;
        $category_id = isset( $data['category_id'] ) ? (int) $data['category_id'] : 0;
        $amount      = isset( $data['budget_amount'] ) ? (float) $data['budget_amount'] : 0;

        $area_name = '';
        if ( $area_id ) {
            $areas_table = $wpdb->prefix . 'aura_areas';
            $area_name   = (string) $wpdb->get_var( $wpdb->prepare(
                "SELECT name FROM {$areas_table} WHERE id = %d",
                $area_id
            ) );
        }

        $cat_name = '';
        if ( $category_id ) {
            $ct_table = $wpdb->prefix . 'aura_finance_categories';
            $cat_name = (string) $wpdb->get_var( $wpdb->prepare(
                "SELECT name FROM {$ct_table} WHERE id = %d",
                $category_id
            ) );
        }

        // Construir partes del mensaje según los datos disponibles
        $area_label = $area_name ?: sprintf( __( 'Área #%d', 'aura-suite' ), $area_id );
        $cat_part   = $cat_name  ? sprintf( __( ' · Categoría: %s', 'aura-suite' ), $cat_name ) : '';
        $amt_part   = $amount    ? sprintf( ' ($%s)', number_format( $amount, 2, '.', ',' ) )     : '';

        $title = sprintf( __( 'Nuevo presupuesto asignado — %s', 'aura-suite' ), $area_label );
        $msg   = sprintf(
            __( 'Se asignó un nuevo presupuesto para el área "%s"%s%s.', 'aura-suite' ),
            $area_label, $cat_part, $amt_part
        );
        $link = admin_url( 'admin.php?page=aura-financial-budgets' );

        $recipients = get_users( [ 'capability' => 'aura_finance_view_all' ] );
        foreach ( $recipients as $u ) {
            self::notify( $u->ID, 'budget_assigned', $title, $msg, $link );
        }
    }

    public static function on_import_executed( int $total, int $successful, int $failed ): void {
        $uid   = get_current_user_id();
        $title = __( 'Importación completada', 'aura-suite' );
        $msg   = sprintf(
            __( 'Se procesaron %d filas: %d importadas correctamente, %d con error.', 'aura-suite' ),
            $total, $successful, $failed
        );
        $link = admin_url( 'admin.php?page=aura-financial-import' );
        self::notify( $uid, 'import_complete', $title, $msg, $link );
    }

    public static function on_export_executed( string $format, int $count, $filters ): void {
        $uid   = get_current_user_id();
        $title = __( 'Exportación lista para descargar', 'aura-suite' );
        $msg   = sprintf(
            __( 'Se exportaron %d registros en formato %s.', 'aura-suite' ),
            $count, strtoupper( $format )
        );
        $link = admin_url( 'admin.php?page=aura-financial-export' );
        self::notify( $uid, 'export_ready', $title, $msg, $link );
    }

    /* ============================================================
     * Admin Bar — Icono Campana
     * ============================================================ */

    public static function add_admin_bar_bell( WP_Admin_Bar $wp_admin_bar ): void {
        if ( ! is_admin_bar_showing() ) return;
        $uid = get_current_user_id();
        if ( ! $uid ) return;

        $count     = self::get_unread_count( $uid );
        $notif_url = admin_url( 'admin.php?page=aura-financial-notifications' );

        $badge = $count > 0
            ? '<span class="aura-bell-badge">' . ( $count > 99 ? '99+' : $count ) . '</span>'
            : '';

        $wp_admin_bar->add_node( [
            'id'    => 'aura-notifications-bell',
            'title' => '<span class="ab-icon dashicons dashicons-bell" aria-hidden="true"></span>'
                     . '<span class="ab-label">' . __( 'Notificaciones', 'aura-suite' ) . '</span>'
                     . $badge,
            'href'  => $notif_url,
            'meta'  => [ 'class' => 'aura-admin-bar-notifications' ],
        ] );
    }

    /* ============================================================
     * AJAX: obtener notificaciones para dropdown (top 10)
     * ============================================================ */

    public static function ajax_get_notifications(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        $uid   = get_current_user_id();
        $items = self::get_notifications( $uid, 10 );
        $unread = self::get_unread_count( $uid );
        wp_send_json_success( [ 'items' => $items, 'unread' => $unread ] );
    }

    /* ============================================================
     * AJAX: página completa paginada
     * ============================================================ */

    public static function ajax_get_all_notifications(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        $uid        = get_current_user_id();
        $page       = max( 1, (int) ( $_POST['page'] ?? 1 ) );
        $per_page   = 20;
        $filter_type= sanitize_key( $_POST['filter_type'] ?? '' );
        $filter_read= isset( $_POST['filter_read'] ) ? (string) $_POST['filter_read'] : '';

        global $wpdb;
        $table  = $wpdb->prefix . self::TABLE;
        $where  = $wpdb->prepare( 'user_id = %d', $uid );
        if ( $filter_type ) $where .= $wpdb->prepare( ' AND type = %s', $filter_type );
        if ( $filter_read === '0' ) $where .= ' AND is_read = 0';
        if ( $filter_read === '1' ) $where .= ' AND is_read = 1';

        $total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}" );
        $offset = ( $page - 1 ) * $per_page;
        $rows   = $wpdb->get_results(
            "SELECT * FROM `{$table}` WHERE {$where} ORDER BY created_at DESC LIMIT {$per_page} OFFSET {$offset}"
        );

        $types = self::get_types();
        foreach ( $rows as $r ) {
            $r->type_label = $types[ $r->type ] ?? $r->type;
        }

        wp_send_json_success( [
            'items'  => $rows,
            'total'  => $total,
            'pages'  => (int) ceil( $total / $per_page ),
            'page'   => $page,
            'unread' => self::get_unread_count( $uid ),
        ] );
    }

    /* ============================================================
     * AJAX: marcar una notificación como leída
     * ============================================================ */

    public static function ajax_mark_read(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        $uid = get_current_user_id();
        $id  = (int) ( $_POST['id'] ?? 0 );

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . self::TABLE,
            [ 'is_read' => 1 ],
            [ 'id' => $id, 'user_id' => $uid ],
            [ '%d' ], [ '%d', '%d' ]
        );
        wp_send_json_success( [ 'unread' => self::get_unread_count( $uid ) ] );
    }

    /* ============================================================
     * AJAX: marcar todas como leídas
     * ============================================================ */

    public static function ajax_mark_all_read(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        $uid = get_current_user_id();

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . self::TABLE,
            [ 'is_read' => 1 ],
            [ 'user_id' => $uid, 'is_read' => 0 ],
            [ '%d' ], [ '%d', '%d' ]
        );
        wp_send_json_success( [ 'unread' => 0 ] );
    }

    /* ============================================================
     * AJAX: eliminar notificación
     * ============================================================ */

    public static function ajax_delete(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        $uid = get_current_user_id();
        $id  = (int) ( $_POST['id'] ?? 0 );

        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . self::TABLE,
            [ 'id' => $id, 'user_id' => $uid ],
            [ '%d', '%d' ]
        );
        wp_send_json_success( [ 'unread' => self::get_unread_count( $uid ) ] );
    }

    /* ============================================================
     * AJAX: guardar preferencias de notificación
     * ============================================================ */

    public static function ajax_save_prefs(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        $uid = get_current_user_id();

        $bool_keys = [
            'email_transaction_approval',
            'email_transaction_result',
            'email_budget_alert',
            'email_reminders',
            'email_system',
            'no_disturb_weekend',
            'no_disturb_hours',
        ];

        $prefs = get_user_meta( $uid, self::PREFS_META_KEY, true );
        if ( ! is_array( $prefs ) ) $prefs = [];

        foreach ( $bool_keys as $k ) {
            $prefs[ $k ] = ! empty( $_POST[ $k ] );
        }

        $freq = sanitize_key( $_POST['email_frequency'] ?? 'immediate' );
        if ( ! in_array( $freq, [ 'immediate', 'daily', 'weekly' ], true ) ) $freq = 'immediate';
        $prefs['email_frequency'] = $freq;

        update_user_meta( $uid, self::PREFS_META_KEY, $prefs );
        wp_send_json_success( [ 'message' => __( 'Preferencias guardadas.', 'aura-suite' ) ] );
    }

    /* ============================================================
     * Cron: recordatorios diarios
     * ============================================================ */

    public static function cron_daily_reminders(): void {
        global $wpdb;

        // Usuarios con acceso financiero
        $users = get_users( [
            'capability' => 'aura_finance_view_own',
            'fields'     => [ 'ID' ],
        ] );

        $tx_table = $wpdb->prefix . 'aura_finance_transactions';
        if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$tx_table}'" ) ) return;

        foreach ( $users as $u ) {
            $uid = (int) $u->ID;
            $prefs = get_user_meta( $uid, self::PREFS_META_KEY, true );
            if ( is_array( $prefs ) && isset( $prefs['email_reminders'] ) && ! $prefs['email_reminders'] ) {
                continue; // usuario deshabilitó recordatorios
            }

            // Transacciones pendientes > 7 días
            $pending = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$tx_table}`
                 WHERE created_by = %d
                   AND status = 'pending'
                   AND created_at < %s",
                $uid, date( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
            ) );

            if ( $pending > 0 ) {
                self::notify(
                    $uid,
                    'reminder_pending',
                    sprintf( __( 'Tienes %d transacciones pendientes sin acción', 'aura-suite' ), $pending ),
                    sprintf( __( '%d transacciones llevan más de 7 días en estado pendiente. Por favor revísalas.', 'aura-suite' ), $pending ),
                    admin_url( 'admin.php?page=aura-financial-pending' ),
                    true
                );
            }

            // Transacciones rechazadas sin acción en últimos 7 días
            $rejected = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$tx_table}`
                 WHERE created_by = %d
                   AND status = 'rejected'
                   AND updated_at < %s",
                $uid, date( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
            ) );

            if ( $rejected > 0 ) {
                self::notify(
                    $uid,
                    'reminder_rejected',
                    sprintf( __( 'Tienes %d transacciones rechazadas sin atender', 'aura-suite' ), $rejected ),
                    sprintf( __( '%d transacciones rechazadas llevan más de 7 días sin ser revisadas.', 'aura-suite' ), $rejected ),
                    admin_url( 'admin.php?page=aura-financial-transactions' ),
                    true
                );
            }
        }

        // Comprobar presupuestos al 80% — notificar a admins
        $budget_table = $wpdb->prefix . 'aura_finance_budgets';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$budget_table}'" ) ) {
            $at_risk = $wpdb->get_results(
                "SELECT b.id, b.budget_amount, b.spent_amount
                 FROM `{$budget_table}` b
                 WHERE b.is_active = 1
                   AND b.spent_amount >= b.budget_amount * 0.8
                   AND b.spent_amount < b.budget_amount"
            );

            if ( $at_risk ) {
                $admins = get_users( [ 'role' => 'administrator', 'fields' => [ 'ID' ] ] );
                foreach ( $at_risk as $budget ) {
                    $pct = $budget->budget_amount > 0
                        ? round( $budget->spent_amount / $budget->budget_amount * 100, 1 )
                        : 0;
                    $title = sprintf( __( 'Presupuesto #%d al %s%%', 'aura-suite' ), $budget->id, $pct );
                    $msg   = sprintf(
                        __( 'El presupuesto #%d ha alcanzado el %s%% de su límite.', 'aura-suite' ),
                        $budget->id, $pct
                    );
                    foreach ( $admins as $a ) {
                        self::notify( (int) $a->ID, 'budget_warning', $title, $msg,
                            admin_url( 'admin.php?page=aura-financial-budgets' ) );
                    }
                }
            }
        }
    }

    /* ============================================================
     * Cron: resumen semanal
     * ============================================================ */

    public static function cron_weekly_summary(): void {
        global $wpdb;
        $tx_table = $wpdb->prefix . 'aura_finance_transactions';
        if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$tx_table}'" ) ) return;

        $week_start = date( 'Y-m-d', strtotime( '-7 days' ) );
        $week_end   = date( 'Y-m-d' );

        $total_income  = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(amount) FROM `{$tx_table}`
             WHERE transaction_type = 'income' AND status = 'approved'
               AND transaction_date BETWEEN %s AND %s",
            $week_start, $week_end
        ) );

        $total_expense = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(amount) FROM `{$tx_table}`
             WHERE transaction_type = 'expense' AND status = 'approved'
               AND transaction_date BETWEEN %s AND %s",
            $week_start, $week_end
        ) );

        if ( $total_income <= 0 && $total_expense <= 0 ) return;

        $title = sprintf( __( 'Resumen semanal: %s — %s', 'aura-suite' ), $week_start, $week_end );
        $msg   = sprintf(
            __( 'Semana %s al %s | Ingresos: $%s | Gastos: $%s | Balance: $%s', 'aura-suite' ),
            $week_start, $week_end,
            number_format( $total_income, 2 ),
            number_format( $total_expense, 2 ),
            number_format( $total_income - $total_expense, 2 )
        );

        $admins = get_users( [ 'role' => 'administrator', 'fields' => [ 'ID' ] ] );
        foreach ( $admins as $a ) {
            self::notify( (int) $a->ID, 'settings_updated', $title, $msg,
                admin_url( 'admin.php?page=aura-financial-dashboard' ) );
        }
    }

    /* ============================================================
     * Envío de Email HTML
     * ============================================================ */

    private static function send_email( int $user_id, string $title, string $message, string $link = '' ): bool {
        $user = get_userdata( $user_id );
        if ( ! $user ) return false;

        $site_name = aura_get_org_name();
        $notif_url = admin_url( 'admin.php?page=aura-financial-notifications' );
        $logo_html = '';
        if ( get_option( 'aura_org_logo_in_email', true ) && (int) get_option( 'aura_org_logo_id', 0 ) > 0 ) {
            $logo_html = '<div style="text-align:center;margin-bottom:12px;"><img src="' . esc_url( aura_get_org_logo_url( 'medium' ) ) . '" alt="' . esc_attr( $site_name ) . '" style="max-height:70px;width:auto;"></div>';
        }

        $btn = $link
            ? '<p style="text-align:center;margin-top:24px;">
                 <a href="' . esc_url( $link ) . '"
                    style="display:inline-block;padding:12px 28px;background:#2271b1;color:#fff;text-decoration:none;border-radius:6px;font-size:15px;">
                    ' . esc_html__( 'Ver detalle', 'aura-suite' ) . '
                 </a>
               </p>'
            : '';

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>
            body{font-family:Arial,sans-serif;background:#f0f0f1;margin:0;padding:0;}
            .wrap{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.12);}
            .hdr{background:linear-gradient(135deg,#2271b1,#135e96);padding:28px 24px;text-align:center;}
            .hdr h1{color:#fff;margin:8px 0 0;font-size:20px;}
            .body{padding:28px 32px;color:#1d2327;font-size:15px;line-height:1.6;}
            .msg-box{background:#f6f7f7;border-left:4px solid #2271b1;padding:14px 18px;border-radius:0 6px 6px 0;margin:16px 0;}
            .ftr{background:#f6f7f7;padding:14px 24px;text-align:center;font-size:12px;color:#777;border-top:1px solid #e0e0e0;}
            .ftr a{color:#2271b1;}
        </style>
        </head><body>
        <div class="wrap">
          <div class="hdr">
            ' . $logo_html . '
            <h1>' . esc_html( $site_name ) . ' &mdash; ' . esc_html__( 'Notificación', 'aura-suite' ) . '</h1>
          </div>
          <div class="body">
            <h2 style="margin-top:0;">' . esc_html( $title ) . '</h2>
            <div class="msg-box">' . nl2br( esc_html( $message ) ) . '</div>
            ' . $btn . '
          </div>
          <div class="ftr">
            <p>' . sprintf( esc_html__( 'Eres %s en %s.', 'aura-suite' ), esc_html( $user->display_name ), esc_html( $site_name ) ) . '</p>
            <p><a href="' . esc_url( $notif_url ) . '">' . esc_html__( 'Gestionar preferencias de notificación', 'aura-suite' ) . '</a></p>
            <p>&copy; ' . date( 'Y' ) . ' ' . esc_html( $site_name ) . '</p>
          </div>
        </div>
        </body></html>';

        add_filter( 'wp_mail_content_type', fn() => 'text/html' );
        $sent = wp_mail( $user->user_email, '[' . $site_name . '] ' . $title, $html );
        remove_filter( 'wp_mail_content_type', fn() => 'text/html' );

        return $sent;
    }

    /* ============================================================
     * Helpers query
     * ============================================================ */

    public static function get_unread_count( int $user_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$wpdb->prefix}" . self::TABLE . "` WHERE user_id = %d AND is_read = 0",
            $user_id
        ) );
    }

    public static function get_notifications( int $user_id, int $limit = 10 ): array {
        global $wpdb;
        $table  = $wpdb->prefix . self::TABLE;
        $types  = self::get_types();
        $rows   = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id, $limit
        ) );
        foreach ( $rows as $r ) {
            $r->type_label = $types[ $r->type ] ?? $r->type;
            $r->time_ago   = self::time_ago( $r->created_at );
        }
        return $rows;
    }

    private static function get_pref( int $user_id, string $key, $default = null ) {
        $prefs = get_user_meta( $user_id, self::PREFS_META_KEY, true );
        if ( ! is_array( $prefs ) ) return $default;
        return $prefs[ $key ] ?? $default;
    }

    private static function user_wants_email( int $user_id, string $type ): bool {
        // Mapeo tipo → preferencia
        $map = [
            'transaction_pending'  => 'email_transaction_approval',
            'transaction_approved' => 'email_transaction_result',
            'transaction_rejected' => 'email_transaction_result',
            'transaction_edited'   => 'email_transaction_result',
            'budget_warning'       => 'email_budget_alert',
            'budget_exceeded'      => 'email_budget_alert',
            'budget_assigned'      => 'email_budget_alert',
            'reminder_pending'     => 'email_reminders',
            'reminder_no_receipt'  => 'email_reminders',
            'reminder_rejected'    => 'email_reminders',
            'import_complete'      => 'email_system',
            'export_ready'         => 'email_system',
            'settings_updated'     => 'email_system',
        ];
        $pref_key = $map[ $type ] ?? 'email_system';
        $val      = self::get_pref( $user_id, $pref_key, true ); // default ON
        return (bool) $val;
    }

    private static function is_do_not_disturb( int $user_id ): bool {
        $no_weekend = self::get_pref( $user_id, 'no_disturb_weekend', false );
        $no_hours   = self::get_pref( $user_id, 'no_disturb_hours', false );

        if ( $no_weekend ) {
            $dow = (int) date( 'N' ); // 6=sábado, 7=domingo
            if ( $dow >= 6 ) return true;
        }
        if ( $no_hours ) {
            $hour = (int) date( 'G' );
            if ( $hour >= 18 || $hour < 8 ) return true;
        }
        return false;
    }

    private static function time_ago( string $datetime ): string {
        $diff = time() - strtotime( $datetime );
        if ( $diff < 60 )    return __( 'hace un momento', 'aura-suite' );
        if ( $diff < 3600 )  return sprintf( __( 'hace %d min', 'aura-suite' ),  (int) ( $diff / 60 ) );
        if ( $diff < 86400 ) return sprintf( __( 'hace %d h', 'aura-suite' ),    (int) ( $diff / 3600 ) );
        return sprintf( __( 'hace %d días', 'aura-suite' ), (int) ( $diff / 86400 ) );
    }

    /* ============================================================
     * Render
     * ============================================================ */

    public static function render(): void {
        include AURA_PLUGIN_DIR . 'templates/financial/notifications-page.php';
    }
}
