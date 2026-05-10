<?php
/**
 * Reservas de Libros — Fase 4
 *
 * Permite reservar un libro sin copias disponibles. Gestiona la cola de reservas,
 * notificaciones al liberar una copia, y expiración automática vía cron.
 *
 * @package Aura_Business_Suite
 * @subpackage Library
 * @since 1.7.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Library_Reservations {

    const NONCE = 'aura_library_nonce';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        $ajax_actions = [
            'get_list'   => 'ajax_get_list',
            'create'     => 'ajax_create',
            'cancel'     => 'ajax_cancel',
            'get_detail' => 'ajax_get_detail',
        ];

        foreach ( $ajax_actions as $action => $handler ) {
            add_action( 'wp_ajax_aura_library_reservations_' . $action, [ __CLASS__, $handler ] );
        }

        // Cron diario para expirar reservas viejas
        add_action( 'aura_library_expire_reservations', [ __CLASS__, 'expire_old' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────

    public static function render_list(): void {
        $can = current_user_can( 'aura_library_view_loans_all' )
            || current_user_can( 'manage_options' );
        if ( ! $can ) {
            wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'aura-business-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/library/reservations-list.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Listado paginado
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_list(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_library_view_loans_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ], 403 );
        }

        global $wpdb;

        $page     = max( 1, absint( $_POST['page'] ?? 1 ) );
        $per_page = min( 100, max( 5, absint( $_POST['per_page'] ?? 20 ) ) );
        $offset   = ( $page - 1 ) * $per_page;
        $search   = sanitize_text_field( $_POST['search'] ?? '' );
        $status   = sanitize_key( $_POST['status'] ?? '' );

        $t_res   = $wpdb->prefix . 'aura_library_reservations';
        $t_books = $wpdb->prefix . 'aura_library_books';

        $where   = "WHERE r.status != 'cancelled'";
        $params  = [];

        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where  .= ' AND (b.title LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ( $status ) {
            $where  .= ' AND r.status = %s';
            $params[] = $status;
        }

        $select = "
            SELECT r.id, r.book_id, r.user_id, r.reserved_at, r.notified_at,
                   r.expires_at, r.status, r.notes,
                   b.title AS book_title, b.dewey_number, b.available_copies,
                   u.display_name AS user_name, u.user_email,
                   (
                       SELECT COUNT(*) + 1
                       FROM {$t_res} rq
                       WHERE rq.book_id = r.book_id
                         AND rq.status = 'waiting'
                         AND rq.id < r.id
                   ) AS queue_position
            FROM {$t_res} r
            INNER JOIN {$t_books} b ON b.id = r.book_id
            INNER JOIN {$wpdb->users} u ON u.ID = r.user_id
            {$where}
            ORDER BY r.reserved_at DESC
        ";

        $count_sql = "
            SELECT COUNT(*)
            FROM {$t_res} r
            INNER JOIN {$t_books} b ON b.id = r.book_id
            INNER JOIN {$wpdb->users} u ON u.ID = r.user_id
            {$where}
        ";

        if ( $params ) {
            $total       = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
            $items_query = $wpdb->prepare( $select . ' LIMIT %d OFFSET %d', array_merge( $params, [ $per_page, $offset ] ) );
        } else {
            $total       = (int) $wpdb->get_var( $count_sql );
            $items_query = $wpdb->prepare( $select . ' LIMIT %d OFFSET %d', $per_page, $offset );
        }

        $items = $wpdb->get_results( $items_query ) ?: [];

        // Detectar si notified_at pero aún no cancelada/expirada
        $today = current_time( 'Y-m-d H:i:s' );
        foreach ( $items as &$item ) {
            if ( $item->status === 'waiting' && $item->expires_at && $item->expires_at < $today ) {
                $item->status = 'expired';
            }
            $item->can_cancel = ( $item->status === 'waiting' || $item->status === 'notified' );
        }
        unset( $item );

        wp_send_json_success( [
            'items'       => $items,
            'total'       => $total,
            'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
            'page'        => $page,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Crear reserva
    // ─────────────────────────────────────────────────────────────

    public static function ajax_create(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_library_loan_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para reservar.', 'aura-business-suite' ) ], 403 );
        }

        global $wpdb;

        $book_id = absint( $_POST['book_id'] ?? 0 );
        $user_id = absint( $_POST['user_id'] ?? get_current_user_id() );
        $notes   = sanitize_textarea_field( $_POST['notes'] ?? '' );

        if ( ! $book_id ) {
            wp_send_json_error( [ 'message' => __( 'Libro no válido.', 'aura-business-suite' ) ] );
        }

        $t_books = $wpdb->prefix . 'aura_library_books';
        $t_res   = $wpdb->prefix . 'aura_library_reservations';

        // Verificar que el libro existe
        $book = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, title, status, available_copies FROM {$t_books} WHERE id = %d AND deleted_at IS NULL",
            $book_id
        ) );

        if ( ! $book ) {
            wp_send_json_error( [ 'message' => __( 'Libro no encontrado.', 'aura-business-suite' ) ] );
        }

        if ( $book->status === 'reference_only' ) {
            wp_send_json_error( [ 'message' => __( 'Este libro es de solo consulta y no puede reservarse.', 'aura-business-suite' ) ] );
        }

        // Verificar que no tenga ya una reserva activa para este libro
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$t_res} WHERE book_id = %d AND user_id = %d AND status IN ('waiting','notified')",
            $book_id,
            $user_id
        ) );

        if ( $existing ) {
            wp_send_json_error( [ 'message' => __( 'Ya tienes una reserva activa para este libro.', 'aura-business-suite' ) ] );
        }

        // Calcular fecha de expiración (días configurables, default 7)
        $expire_days = absint( get_option( 'aura_library_reservation_expire_days', 7 ) );
        $expires_at  = $expire_days > 0
            ? gmdate( 'Y-m-d H:i:s', strtotime( "+{$expire_days} days" ) )
            : null;

        $inserted = $wpdb->insert( $t_res, [
            'book_id'     => $book_id,
            'user_id'     => $user_id,
            'reserved_at' => current_time( 'mysql' ),
            'expires_at'  => $expires_at,
            'status'      => 'waiting',
            'notes'       => $notes ?: null,
        ], [ '%d', '%d', '%s', '%s', '%s', '%s' ] );

        if ( ! $inserted ) {
            wp_send_json_error( [ 'message' => __( 'Error al crear la reserva.', 'aura-business-suite' ) ] );
        }

        $reservation_id = (int) $wpdb->insert_id;

        // Auditoría
        self::log_audit( get_current_user_id(), 'reserve_book', 'reservation', $reservation_id,
            null, wp_json_encode( [ 'book_id' => $book_id, 'user_id' => $user_id, 'expires_at' => $expires_at ] ) );

        // Calcular posición en cola
        $queue_pos = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$t_res} WHERE book_id = %d AND status = 'waiting' AND id <= %d",
            $book_id,
            $reservation_id
        ) );

        wp_send_json_success( [
            'reservation_id' => $reservation_id,
            'queue_position' => $queue_pos,
            'message'        => sprintf(
                /* translators: %d queue position number */
                __( 'Reserva creada. Estás en la posición %d de la cola.', 'aura-business-suite' ),
                $queue_pos
            ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Cancelar reserva
    // ─────────────────────────────────────────────────────────────

    public static function ajax_cancel(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        global $wpdb;
        $t_res = $wpdb->prefix . 'aura_library_reservations';

        $reservation_id = absint( $_POST['reservation_id'] ?? 0 );
        if ( ! $reservation_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de reserva no válido.', 'aura-business-suite' ) ] );
        }

        $res = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t_res} WHERE id = %d",
            $reservation_id
        ) );

        if ( ! $res ) {
            wp_send_json_error( [ 'message' => __( 'Reserva no encontrada.', 'aura-business-suite' ) ] );
        }

        // Solo puede cancelar el propio usuario o un admin/bibliotecario
        $is_own   = (int) $res->user_id === get_current_user_id();
        $can_all  = current_user_can( 'aura_library_view_loans_all' ) || current_user_can( 'manage_options' );
        if ( ! $is_own && ! $can_all ) {
            wp_send_json_error( [ 'message' => __( 'No puedes cancelar esta reserva.', 'aura-business-suite' ) ], 403 );
        }

        if ( ! in_array( $res->status, [ 'waiting', 'notified' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Esta reserva no puede cancelarse.', 'aura-business-suite' ) ] );
        }

        $wpdb->update( $t_res,
            [ 'status' => 'cancelled' ],
            [ 'id' => $reservation_id ],
            [ '%s' ],
            [ '%d' ]
        );

        self::log_audit( get_current_user_id(), 'cancel_reservation', 'reservation', $reservation_id,
            wp_json_encode( [ 'status' => $res->status ] ), wp_json_encode( [ 'status' => 'cancelled' ] ) );

        wp_send_json_success( [ 'message' => __( 'Reserva cancelada.', 'aura-business-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Detalle de reserva
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_detail(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        global $wpdb;
        $t_res   = $wpdb->prefix . 'aura_library_reservations';
        $t_books = $wpdb->prefix . 'aura_library_books';

        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID no válido.', 'aura-business-suite' ) ] );
        }

        $res = $wpdb->get_row( $wpdb->prepare(
            "SELECT r.*, b.title AS book_title, b.dewey_number, b.author,
                    b.available_copies, b.total_copies,
                    u.display_name AS user_name, u.user_email
             FROM {$t_res} r
             INNER JOIN {$t_books} b ON b.id = r.book_id
             INNER JOIN {$wpdb->users} u ON u.ID = r.user_id
             WHERE r.id = %d",
            $id
        ) );

        if ( ! $res ) {
            wp_send_json_error( [ 'message' => __( 'Reserva no encontrada.', 'aura-business-suite' ) ] );
        }

        // Verificar permiso
        $is_own  = (int) $res->user_id === get_current_user_id();
        $can_all = current_user_can( 'aura_library_view_loans_all' ) || current_user_can( 'manage_options' );
        if ( ! $is_own && ! $can_all ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ], 403 );
        }

        // Posición en la cola
        $queue_pos = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$t_res}
             WHERE book_id = %d AND status = 'waiting' AND id <= %d",
            $res->book_id, $id
        ) );

        $res->queue_position = $queue_pos;
        $res->can_cancel     = in_array( $res->status, [ 'waiting', 'notified' ], true );

        wp_send_json_success( [ 'reservation' => $res ] );
    }

    // ─────────────────────────────────────────────────────────────
    // PÚBLICO — Notificar al siguiente en la cola
    // ─────────────────────────────────────────────────────────────

    /**
     * Llama a este método tras liberar una copia de un libro (en ajax_return_book).
     * Busca la primera reserva 'waiting' del libro y la marca como 'notified',
     * enviando notificación al lector.
     *
     * @param int $book_id ID del libro que acaba de tener una copia disponible.
     * @return bool True si se notificó a alguien.
     */
    public static function notify_next_in_queue( int $book_id ): bool {
        global $wpdb;
        $t_res = $wpdb->prefix . 'aura_library_reservations';

        $next = $wpdb->get_row( $wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_email
             FROM {$t_res} r
             INNER JOIN {$wpdb->users} u ON u.ID = r.user_id
             WHERE r.book_id = %d AND r.status = 'waiting'
             ORDER BY r.id ASC
             LIMIT 1",
            $book_id
        ) );

        if ( ! $next ) {
            return false;
        }

        // Calcular nueva fecha de expiración desde ahora
        $expire_days = absint( get_option( 'aura_library_reservation_expire_days', 7 ) );
        $expires_at  = $expire_days > 0
            ? gmdate( 'Y-m-d H:i:s', strtotime( "+{$expire_days} days" ) )
            : null;

        $wpdb->update( $t_res,
            [
                'status'      => 'notified',
                'notified_at' => current_time( 'mysql' ),
                'expires_at'  => $expires_at,
            ],
            [ 'id' => (int) $next->id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        // Enviar notificación (integra con el sistema global de Aura Suite)
        self::send_availability_notification( $next, $book_id, $expires_at );

        self::log_audit( 0, 'reservation_notified', 'reservation', (int) $next->id,
            wp_json_encode( [ 'status' => 'waiting' ] ),
            wp_json_encode( [ 'status' => 'notified', 'notified_at' => current_time( 'mysql' ) ] )
        );

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // PÚBLICO — Expirar reservas viejas (cron)
    // ─────────────────────────────────────────────────────────────

    /**
     * Expira reservas 'waiting' o 'notified' cuya expires_at ha pasado.
     * Se ejecuta vía cron diario `aura_library_expire_reservations`.
     *
     * @return int Número de reservas expiradas.
     */
    public static function expire_old(): int {
        global $wpdb;
        $t_res = $wpdb->prefix . 'aura_library_reservations';

        $now = current_time( 'mysql' );

        // Obtener IDs a expirar para log
        $to_expire = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$t_res}
             WHERE status IN ('waiting','notified')
               AND expires_at IS NOT NULL
               AND expires_at < %s",
            $now
        ) ) ?: [];

        if ( empty( $to_expire ) ) {
            return 0;
        }

        $placeholders = implode( ',', array_fill( 0, count( $to_expire ), '%d' ) );
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$t_res} SET status = 'expired' WHERE id IN ({$placeholders})",
                $to_expire
            )
        );

        $count = count( $to_expire );

        // Auditoría masiva
        self::log_audit( 0, 'reservation_expired_batch', 'reservation', 0,
            null,
            wp_json_encode( [ 'expired_ids' => $to_expire, 'count' => $count ] )
        );

        return $count;
    }

    // ─────────────────────────────────────────────────────────────
    // PÚBLICO — Métodos de consulta para otras fases
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene reservas activas de un usuario (Fase 7 / integración Estudiantes).
     *
     * @param int $user_id WordPress user ID.
     * @return array
     */
    public static function get_active_by_user( int $user_id ): array {
        global $wpdb;
        $t_res   = $wpdb->prefix . 'aura_library_reservations';
        $t_books = $wpdb->prefix . 'aura_library_books';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT r.id, r.book_id, r.reserved_at, r.expires_at, r.status,
                    b.title AS book_title
             FROM {$t_res} r
             INNER JOIN {$t_books} b ON b.id = r.book_id
             WHERE r.user_id = %d AND r.status IN ('waiting','notified')
             ORDER BY r.reserved_at ASC",
            $user_id
        ) ) ?: [];
    }

    /**
     * Cuenta cuántos usuarios están en la cola de un libro.
     *
     * @param int $book_id
     * @return int
     */
    public static function get_queue_count( int $book_id ): int {
        global $wpdb;
        $t_res = $wpdb->prefix . 'aura_library_reservations';

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$t_res} WHERE book_id = %d AND status = 'waiting'",
            $book_id
        ) );
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVADO — Notificación de disponibilidad
    // ─────────────────────────────────────────────────────────────

    private static function send_availability_notification( object $reservation, int $book_id, ?string $expires_at ): void {
        // Obtener título del libro
        global $wpdb;
        $t_books = $wpdb->prefix . 'aura_library_books';
        $title   = $wpdb->get_var( $wpdb->prepare(
            "SELECT title FROM {$t_books} WHERE id = %d",
            $book_id
        ) ) ?: '#' . $book_id;

        $site_name = get_bloginfo( 'name' );
        $exp_str   = $expires_at ? wp_date( get_option( 'date_format' ), strtotime( $expires_at ) ) : '';

        $subject = sprintf(
            /* translators: 1: book title, 2: site name */
            __( '[%2$s] El libro "%1$s" ya está disponible para ti', 'aura-business-suite' ),
            $title,
            $site_name
        );

        $body = sprintf(
            /* translators: 1: user name, 2: book title, 3: expiry date */
            __( 'Hola %1$s,\n\nEl libro "%2$s" que reservaste ya está disponible. Tienes hasta el %3$s para retirarlo, de lo contrario la reserva se cancelará automáticamente.\n\nGracias.', 'aura-business-suite' ),
            $reservation->display_name,
            $title,
            $exp_str ?: __( 'próximamente', 'aura-business-suite' )
        );

        // Usar el sistema de notificaciones global si está disponible
        if ( class_exists( 'Aura_Notifications' ) && method_exists( 'Aura_Notifications', 'send_email' ) ) {
            Aura_Notifications::send_email( $reservation->user_email, $subject, $body );
        } else {
            wp_mail(
                $reservation->user_email,
                $subject,
                $body
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVADO — Auditoría
    // ─────────────────────────────────────────────────────────────

    private static function log_audit( int $user_id, string $action, string $entity_type, int $entity_id, ?string $old_data, ?string $new_data ): void {
        global $wpdb;
        $t_audit = $wpdb->prefix . 'aura_library_audit';

        $wpdb->insert( $t_audit, [
            'user_id'     => $user_id ?: get_current_user_id(),
            'action'      => $action,
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'old_data'    => $old_data,
            'new_data'    => $new_data,
            'ip_address'  => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
            'created_at'  => current_time( 'mysql' ),
        ], [ '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ] );
    }
}
