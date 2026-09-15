<?php
/**
 * Préstamos y Devoluciones de Libros — Fase 3
 *
 * Ciclo completo: registrar préstamo → extensión → devolución + cálculo de multa.
 * Métodos públicos usados por Fase 7 (integración Estudiantes).
 *
 * @package Aura_Business_Suite
 * @subpackage Library
 * @since 1.7.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Library_Loans {

    const NONCE = 'aura_library_nonce';

    // ─────────────────────────────────────────────────────────────
    // INIT — registrar acciones AJAX
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        $ajax_actions = [
            'get_list'        => 'ajax_get_list',
            'create'          => 'ajax_create',
            'return_book'     => 'ajax_return_book',
            'extend'          => 'ajax_extend',
            'update'          => 'ajax_update',
            'cancel'          => 'ajax_cancel',
            'get_detail'      => 'ajax_get_detail',
            'search_users'    => 'ajax_search_users',
            'student_summary' => 'ajax_student_summary',  // F7.4
        ];

        foreach ( $ajax_actions as $action => $handler ) {
            add_action( 'wp_ajax_aura_library_loans_' . $action, [ __CLASS__, $handler ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Resumen biblioteca para ficha de estudiante (F7.4)
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve préstamos activos + flags de vencido/multa para un usuario WP.
     * Llamado desde la ficha del estudiante en el módulo de Estudiantes.
     */
    public static function ajax_student_summary(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_view_all' ) &&
            ! current_user_can( 'aura_library_view_loans_all' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        $wp_user_id = absint( $_POST['wp_user_id'] ?? 0 );
        if ( ! $wp_user_id ) {
            wp_send_json_success( [ 'loans' => [], 'has_overdue' => false, 'has_fines' => false ] );
        }

        $loans      = self::get_active_loans_by_user( $wp_user_id );
        $has_overdue = self::has_overdue_loans( $wp_user_id );
        $has_fines   = self::has_unpaid_fines( $wp_user_id );

        $formatted = array_map( static function( $l ) {
            return [
                'id'         => (int) $l->id,
                'book_title' => $l->book_title ?? '—',
                'dewey'      => $l->dewey_number ?? '',
                'due_date'   => $l->due_date ?? '',
                'status'     => $l->status ?? 'active',
            ];
        }, $loans );

        wp_send_json_success( [
            'loans'       => $formatted,
            'has_overdue' => $has_overdue,
            'has_fines'   => $has_fines,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────

    public static function render_list(): void {
        $can = current_user_can( 'aura_library_view_loans_all' )
            || current_user_can( 'aura_library_view_loans_own' )
            || current_user_can( 'manage_options' );
        if ( ! $can ) {
            wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'aura-business-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/library/loans-list.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Listado paginado con filtros
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_list(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $can_all = current_user_can( 'aura_library_view_loans_all' ) || current_user_can( 'manage_options' );
        $can_own = current_user_can( 'aura_library_view_loans_own' );
        if ( ! $can_all && ! $can_own ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';
        $t_books = $wpdb->prefix . 'aura_library_books';

        $page      = max( 1, intval( $_POST['page']     ?? 1 ) );
        $per_page  = min( 100, max( 10, intval( $_POST['per_page'] ?? 20 ) ) );
        $offset    = ( $page - 1 ) * $per_page;
        $search    = sanitize_text_field( $_POST['search']     ?? '' );
        $status    = sanitize_text_field( $_POST['status']     ?? '' );
        $date_from = sanitize_text_field( $_POST['date_from']  ?? '' );
        $date_to   = sanitize_text_field( $_POST['date_to']    ?? '' );
        $sort_by   = in_array( $_POST['sort_by'] ?? '', [ 'due_date', 'loan_date', 'created_at' ] )
                     ? sanitize_text_field( $_POST['sort_by'] ) : 'created_at';
        $sort_dir  = ( strtoupper( $_POST['sort_dir'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';

        $where  = [ '1=1' ];
        $params = [];

        // Restringir por usuario si no puede ver todos
        if ( ! $can_all ) {
            $where[]  = "l.borrower_user_id = %d";
            $params[] = get_current_user_id();
        }

        $status_allowed = [ 'active', 'returned', 'overdue', 'lost', 'extended', 'cancelled' ];
        if ( $status && in_array( $status, $status_allowed ) ) {
            $where[]  = 'l.status = %s';
            $params[] = $status;
        }

        if ( $search ) {
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = '(b.title LIKE %s OR b.author LIKE %s OR u.display_name LIKE %s)';
            array_push( $params, $like, $like, $like );
        }

        if ( $date_from ) {
            $where[]  = 'l.loan_date >= %s';
            $params[] = sanitize_text_field( $date_from );
        }
        if ( $date_to ) {
            $where[]  = 'l.loan_date <= %s';
            $params[] = sanitize_text_field( $date_to );
        }

        $where_sql = implode( ' AND ', $where );

        $count_sql = "SELECT COUNT(*) FROM {$t_loans} l
                      LEFT JOIN {$t_books} b ON b.id = l.book_id
                      LEFT JOIN {$wpdb->users} u ON u.ID = l.borrower_user_id
                      WHERE {$where_sql}";

        $total = $params
            ? intval( $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) )
            : intval( $wpdb->get_var( $count_sql ) );

        $data_sql = "SELECT l.*,
                            b.title     AS book_title,
                            b.dewey_number,
                            b.cover_image_id,
                            u.display_name AS borrower_name
                     FROM {$t_loans} l
                     LEFT JOIN {$t_books} b ON b.id = l.book_id
                     LEFT JOIN {$wpdb->users} u ON u.ID = l.borrower_user_id
                     WHERE {$where_sql}
                     ORDER BY l.{$sort_by} {$sort_dir}
                     LIMIT %d OFFSET %d";

        $data_params = array_merge( $params, [ $per_page, $offset ] );
        $rows        = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$data_params ) );

        $today = gmdate( 'Y-m-d' );
        foreach ( $rows as &$row ) {
            // Actualizar estado overdue dinámicamente
            if ( in_array( $row->status, [ 'active', 'extended' ], true ) && $row->due_date < $today ) {
                $row->status = 'overdue';
            }
            $row->status_label    = self::get_status_label( $row->status );
            $row->is_overdue      = ( $row->status === 'overdue' );
            $row->cover_thumb_url = $row->cover_image_id
                ? wp_get_attachment_image_url( (int) $row->cover_image_id, 'thumbnail' )
                : '';
            $row->cover_full_url = $row->cover_image_id
                ? wp_get_attachment_image_url( (int) $row->cover_image_id, 'medium' )
                : '';
            $row->can_return  = current_user_can( 'aura_library_loan_return' ) || current_user_can( 'manage_options' );
            $row->can_extend  = ( current_user_can( 'aura_library_loan_extend' ) || current_user_can( 'manage_options' ) )
                                && in_array( $row->status, [ 'active', 'extended' ], true );
            $row->can_edit    = ( current_user_can( 'aura_library_loan_edit' ) || current_user_can( 'manage_options' ) )
                                && ! in_array( $row->status, [ 'returned', 'cancelled' ], true );
            $row->can_cancel  = ( current_user_can( 'aura_library_loan_delete' ) || current_user_can( 'manage_options' ) )
                                && in_array( $row->status, [ 'active', 'extended', 'overdue' ], true );
            // Multa acumulada si está activo/vencido
            if ( in_array( $row->status, [ 'active', 'overdue', 'extended' ], true ) && ! $row->return_date ) {
                $row->fine_amount = Aura_Library_Fines::calculate_fine( $row->due_date );
            }
        }
        unset( $row );

        wp_send_json_success( [
            'items'       => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total / $per_page ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Crear préstamo
    // ─────────────────────────────────────────────────────────────

    public static function ajax_create(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_library_loan_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para registrar préstamos.', 'aura-business-suite' ) ] );
        }

        $book_id    = intval( $_POST['book_id']            ?? 0 );
        $user_id    = intval( $_POST['borrower_user_id']   ?? 0 );
        $loan_date  = sanitize_text_field( $_POST['loan_date']  ?? '' );
        $due_date   = sanitize_text_field( $_POST['due_date']   ?? '' );
        $notes      = sanitize_textarea_field( $_POST['notes']  ?? '' );

        if ( ! $book_id ) {
            wp_send_json_error( [ 'message' => __( 'Debe seleccionar un libro.', 'aura-business-suite' ) ] );
        }
        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => __( 'Debe seleccionar un lector.', 'aura-business-suite' ) ] );
        }
        if ( ! $loan_date ) {
            $loan_date = gmdate( 'Y-m-d' );
        }
        if ( ! $due_date ) {
            $loan_days = (int) get_option( 'aura_library_loan_days', 14 );
            $due_date  = gmdate( 'Y-m-d', strtotime( "+{$loan_days} days", strtotime( $loan_date ) ) );
        }

        // Validar que due_date >= loan_date
        if ( $due_date < $loan_date ) {
            wp_send_json_error( [ 'message' => __( 'La fecha de devolución no puede ser anterior a la fecha de préstamo.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_books = $wpdb->prefix . 'aura_library_books';
        $t_loans = $wpdb->prefix . 'aura_library_loans';

        // Verificar libro disponible
        $book = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, title, available_copies, status FROM {$t_books} WHERE id = %d AND deleted_at IS NULL",
            $book_id
        ) );

        if ( ! $book ) {
            wp_send_json_error( [ 'message' => __( 'Libro no encontrado.', 'aura-business-suite' ) ] );
        }
        if ( $book->status === 'reference_only' ) {
            wp_send_json_error( [ 'message' => __( 'Este libro es de solo consulta y no puede prestarse.', 'aura-business-suite' ) ] );
        }
        if ( (int) $book->available_copies <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'No hay ejemplares disponibles de este libro. Considere reservarlo.', 'aura-business-suite' ) ] );
        }

        // Verificar usuario WP
        if ( ! get_user_by( 'id', $user_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Usuario no encontrado.', 'aura-business-suite' ) ] );
        }

        // Crear préstamo — transacción atómica con bloqueo de fila
        $wpdb->query( 'START TRANSACTION' );

        $decremented = Aura_Library_Books::decrement_available_copies( $book_id );
        if ( ! $decremented ) {
            $wpdb->query( 'ROLLBACK' );
            wp_send_json_error( [ 'message' => __( 'No hay ejemplares disponibles (concurrencia).', 'aura-business-suite' ) ] );
        }

        $result = $wpdb->insert( $t_loans, [
            'book_id'          => $book_id,
            'borrower_user_id' => $user_id,
            'loan_date'        => $loan_date,
            'due_date'         => $due_date,
            'status'           => 'active',
            'notes'            => $notes ?: null,
            'registered_by'    => get_current_user_id(),
            'fine_amount'      => 0.00,
        ] );

        if ( $result === false ) {
            $wpdb->query( 'ROLLBACK' );
            wp_send_json_error( [ 'message' => __( 'Error al registrar el préstamo.', 'aura-business-suite' ) ] );
        }

        $loan_id = $wpdb->insert_id;
        $wpdb->query( 'COMMIT' );

        Aura_Library_Setup::log(
            'create_loan', 'loan', $loan_id,
            [],
            [ 'book_id' => $book_id, 'user_id' => $user_id, 'loan_date' => $loan_date, 'due_date' => $due_date ]
        );

        // Fase 5: Notificación de confirmación de préstamo
        if ( class_exists( 'Aura_Library_Notifications' ) ) {
            Aura_Library_Notifications::send_loan_confirmation( $loan_id );
        }

        wp_send_json_success( [
            'id'      => $loan_id,
            'message' => __( 'Préstamo registrado correctamente.', 'aura-business-suite' ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Registrar devolución
    // ─────────────────────────────────────────────────────────────

    public static function ajax_return_book(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_library_loan_return' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para registrar devoluciones.', 'aura-business-suite' ) ] );
        }

        $loan_id     = intval( $_POST['loan_id']      ?? 0 );
        $return_date = sanitize_text_field( $_POST['return_date'] ?? gmdate( 'Y-m-d' ) );
        $pay_fine    = ! empty( $_POST['pay_fine'] );
        $to_finance  = ! empty( $_POST['to_finance'] );

        if ( ! $loan_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de préstamo inválido.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';
        $t_books = $wpdb->prefix . 'aura_library_books';

        $loan = $wpdb->get_row( $wpdb->prepare(
            "SELECT l.*, b.title AS book_title
             FROM {$t_loans} l
             LEFT JOIN {$t_books} b ON b.id = l.book_id
             WHERE l.id = %d AND l.status != 'returned'",
            $loan_id
        ) );

        if ( ! $loan ) {
            wp_send_json_error( [ 'message' => __( 'Préstamo no encontrado o ya devuelto.', 'aura-business-suite' ) ] );
        }

        // Calcular multa
        $fine = Aura_Library_Fines::calculate_fine( $loan->due_date, $return_date );
        $fine_transaction_id = null;

        // Registrar pago en Finanzas si aplica
        if ( $pay_fine && $fine > 0 && $to_finance
             && get_option( 'aura_library_fines_to_finance', false )
             && class_exists( 'Aura_Financial_Transactions' ) ) {
            $borrower = get_user_by( 'id', $loan->borrower_user_id );
            $fine_transaction_id = Aura_Library_Fines::register_in_finance(
                $loan_id,
                $fine,
                $loan->book_title ?? '',
                $borrower ? $borrower->display_name : ''
            );
        }

        $update_data = [
            'return_date'             => $return_date,
            'status'                  => 'returned',
            'return_registered_by'    => get_current_user_id(),
            'fine_amount'             => $fine,
            'fine_paid'               => ( $pay_fine && $fine > 0 ) ? 1 : 0,
        ];
        if ( $fine_transaction_id ) {
            $update_data['fine_transaction_id'] = $fine_transaction_id;
        }

        $wpdb->update( $t_loans, $update_data, [ 'id' => $loan_id ] );

        // Liberar copia del libro
        Aura_Library_Books::increment_available_copies( (int) $loan->book_id );

        // Fase 4: notificar al siguiente en la cola de reservas
        if ( class_exists( 'Aura_Library_Reservations' ) ) {
            Aura_Library_Reservations::notify_next_in_queue( (int) $loan->book_id );
        }

        // Fase 5: Notificación de confirmación de devolución
        if ( class_exists( 'Aura_Library_Notifications' ) ) {
            Aura_Library_Notifications::send_return_confirmation( $loan_id, $fine );
        }

        Aura_Library_Setup::log(
            'return_loan', 'loan', $loan_id,
            [ 'status' => $loan->status, 'due_date' => $loan->due_date ],
            [ 'return_date' => $return_date, 'fine' => $fine, 'fine_paid' => $pay_fine ]
        );

        wp_send_json_success( [
            'message' => __( 'Devolución registrada correctamente.', 'aura-business-suite' ),
            'fine'    => $fine,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Extender plazo de préstamo
    // ─────────────────────────────────────────────────────────────

    public static function ajax_extend(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_library_loan_extend' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para extender préstamos.', 'aura-business-suite' ) ] );
        }

        $loan_id = intval( $_POST['loan_id'] ?? 0 );
        if ( ! $loan_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de préstamo inválido.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';

        $loan = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t_loans} WHERE id = %d AND status IN ('active','extended')", $loan_id
        ) );

        if ( ! $loan ) {
            wp_send_json_error( [ 'message' => __( 'Préstamo no encontrado o no se puede extender.', 'aura-business-suite' ) ] );
        }

        $max_extensions  = (int) get_option( 'aura_library_max_extensions', 2 );
        $default_days    = (int) get_option( 'aura_library_extension_days', 7 );
        $extended_count  = (int) $loan->extended_count;

        if ( $extended_count >= $max_extensions ) {
            wp_send_json_error( [
                'message' => sprintf(
                    /* translators: %d: máximo de extensiones */
                    __( 'Este préstamo ya alcanzó el máximo de %d extensiones permitidas.', 'aura-business-suite' ),
                    $max_extensions
                ),
            ] );
        }

        // Días personalizados enviados desde el modal (1-180), con fallback al ajuste global
        $custom_days    = intval( $_POST['days'] ?? 0 );
        $extension_days = ( $custom_days >= 1 && $custom_days <= 180 ) ? $custom_days : $default_days;

        $new_due_date = gmdate( 'Y-m-d', strtotime( "+{$extension_days} days", strtotime( $loan->due_date ) ) );

        $wpdb->update( $t_loans, [
            'due_date'       => $new_due_date,
            'extended_count' => $extended_count + 1,
            'status'         => 'extended',
        ], [ 'id' => $loan_id ] );

        Aura_Library_Setup::log(
            'extend_loan', 'loan', $loan_id,
            [ 'due_date' => $loan->due_date, 'extended_count' => $extended_count ],
            [ 'new_due_date' => $new_due_date, 'extended_count' => $extended_count + 1 ]
        );

        wp_send_json_success( [
            'message'      => __( 'Préstamo extendido correctamente.', 'aura-business-suite' ),
            'new_due_date' => $new_due_date,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Detalle completo de un préstamo
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_detail(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $can_all = current_user_can( 'aura_library_view_loans_all' ) || current_user_can( 'manage_options' );
        $can_own = current_user_can( 'aura_library_view_loans_own' );
        if ( ! $can_all && ! $can_own ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        $loan_id = intval( $_POST['loan_id'] ?? 0 );
        if ( ! $loan_id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';
        $t_books = $wpdb->prefix . 'aura_library_books';

        $loan = $wpdb->get_row( $wpdb->prepare(
            "SELECT l.*, b.title AS book_title, b.author AS book_author,
                    b.dewey_number, b.cover_image_id
             FROM {$t_loans} l
             LEFT JOIN {$t_books} b ON b.id = l.book_id
             WHERE l.id = %d",
            $loan_id
        ) );

        if ( ! $loan ) {
            wp_send_json_error( [ 'message' => __( 'Préstamo no encontrado.', 'aura-business-suite' ) ] );
        }

        // Restringir si es solo_own y no es el dueño
        if ( ! $can_all && (int) $loan->borrower_user_id !== get_current_user_id() ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para ver este préstamo.', 'aura-business-suite' ) ] );
        }

        $today  = gmdate( 'Y-m-d' );
        if ( in_array( $loan->status, [ 'active', 'extended' ], true ) && $loan->due_date < $today ) {
            $loan->status = 'overdue';
        }

        $borrower = get_user_by( 'id', $loan->borrower_user_id );
        $loan->borrower_name  = $borrower ? $borrower->display_name : '—';
        $loan->borrower_email = $borrower ? $borrower->user_email   : '—';
        $loan->status_label   = self::get_status_label( $loan->status );
        $loan->cover_thumb_url = $loan->cover_image_id
            ? wp_get_attachment_image_url( (int) $loan->cover_image_id, 'thumbnail' ) : '';
        $loan->fine_current   = Aura_Library_Fines::calculate_fine( $loan->due_date, $loan->return_date ?: null );

        $registered = get_user_by( 'id', $loan->registered_by );
        $loan->registered_by_name = $registered ? $registered->display_name : '—';

        $max_ext   = (int) get_option( 'aura_library_max_extensions', 2 );
        $loan->can_extend = in_array( $loan->status, [ 'active', 'extended' ], true )
                            && (int) $loan->extended_count < $max_ext
                            && ( current_user_can( 'aura_library_loan_extend' ) || current_user_can( 'manage_options' ) );
        $loan->can_return  = in_array( $loan->status, [ 'active', 'overdue', 'extended' ], true )
                             && ( current_user_can( 'aura_library_loan_return' ) || current_user_can( 'manage_options' ) );
        $loan->can_edit    = ! in_array( $loan->status, [ 'returned', 'cancelled' ], true )
                             && ( current_user_can( 'aura_library_loan_edit' ) || current_user_can( 'manage_options' ) );
        $loan->can_cancel  = in_array( $loan->status, [ 'active', 'extended', 'overdue' ], true )
                             && ( current_user_can( 'aura_library_loan_delete' ) || current_user_can( 'manage_options' ) );

        wp_send_json_success( [ 'loan' => $loan ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Búsqueda de usuarios WP (autocomplete)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_search_users(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_library_loan_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        $q = sanitize_text_field( $_POST['q'] ?? '' );
        if ( strlen( $q ) < 2 ) {
            wp_send_json_success( [] );
        }

        $users = get_users( [
            'search'         => '*' . $q . '*',
            'search_columns' => [ 'display_name', 'user_email', 'user_login' ],
            'number'         => 15,
            'fields'         => [ 'ID', 'display_name', 'user_email' ],
        ] );

        $result = array_map( fn( $u ) => [
            'id'    => $u->ID,
            'name'  => $u->display_name,
            'email' => $u->user_email,
        ], $users );

        wp_send_json_success( $result );
    }

    // ─────────────────────────────────────────────────────────────
    // MÉTODOS PÚBLICOS — para integración con otros módulos (Fase 7)
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener préstamos activos de un usuario.
     *
     * @param int $user_id
     * @return array<object>
     */
    public static function get_active_loans_by_user( int $user_id ): array {
        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';
        $t_books = $wpdb->prefix . 'aura_library_books';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT l.id, l.due_date, l.status, l.extended_count,
                    b.title AS book_title, b.dewey_number
             FROM {$t_loans} l
             LEFT JOIN {$t_books} b ON b.id = l.book_id
             WHERE l.borrower_user_id = %d AND l.status IN ('active','overdue','extended')
             ORDER BY l.due_date ASC",
            $user_id
        ) ) ?: [];
    }

    /**
     * Verificar si un usuario tiene préstamos vencidos.
     *
     * @param int $user_id
     * @return bool
     */
    public static function has_overdue_loans( int $user_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_loans';
        $today = gmdate( 'Y-m-d' );

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE borrower_user_id = %d
               AND status IN ('active','extended','overdue')
               AND due_date < %s",
            $user_id, $today
        ) );

        return $count > 0;
    }

    /**
     * Verificar si un usuario tiene multas sin pagar.
     *
     * @param int $user_id
     * @return bool
     */
    public static function has_unpaid_fines( int $user_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_loans';

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE borrower_user_id = %d AND fine_amount > 0 AND fine_paid = 0",
            $user_id
        ) );

        return $count > 0;
    }

    /**
     * Obtener préstamos vencidos (para cron/reportes).
     *
     * @return array<object>
     */
    public static function get_overdue(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_loans';
        $today = gmdate( 'Y-m-d' );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status IN ('active','extended') AND due_date < %s",
            $today
        ) ) ?: [];
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Editar datos de un préstamo
    // ─────────────────────────────────────────────────────────────

    public static function ajax_update(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_library_loan_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para editar préstamos.', 'aura-business-suite' ) ] );
        }

        $loan_id   = intval( $_POST['loan_id']   ?? 0 );
        $loan_date = sanitize_text_field( $_POST['loan_date'] ?? '' );
        $due_date  = sanitize_text_field( $_POST['due_date']  ?? '' );
        $notes     = sanitize_textarea_field( $_POST['notes'] ?? '' );
        $status    = sanitize_text_field( $_POST['status']    ?? '' );

        if ( ! $loan_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de préstamo inválido.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';

        $loan = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t_loans} WHERE id = %d",
            $loan_id
        ) );

        if ( ! $loan ) {
            wp_send_json_error( [ 'message' => __( 'Préstamo no encontrado.', 'aura-business-suite' ) ] );
        }

        if ( in_array( $loan->status, [ 'returned', 'cancelled' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'No se puede editar un préstamo devuelto o cancelado.', 'aura-business-suite' ) ] );
        }

        $update_data = [];
        $ref_loan    = $loan_date ?: $loan->loan_date;

        if ( $loan_date ) {
            $update_data['loan_date'] = $loan_date;
        }
        if ( $due_date ) {
            if ( $due_date < $ref_loan ) {
                wp_send_json_error( [ 'message' => __( 'La fecha de devolución no puede ser anterior a la fecha de préstamo.', 'aura-business-suite' ) ] );
            }
            $update_data['due_date'] = $due_date;
        }
        if ( isset( $_POST['notes'] ) ) {
            $update_data['notes'] = $notes ?: null;
        }

        $allowed_statuses = [ 'active', 'extended', 'overdue', 'lost' ];
        if ( $status && in_array( $status, $allowed_statuses, true ) ) {
            $update_data['status'] = $status;
        }

        if ( empty( $update_data ) ) {
            wp_send_json_error( [ 'message' => __( 'No hay cambios que guardar.', 'aura-business-suite' ) ] );
        }

        $old_data = [];
        foreach ( array_keys( $update_data ) as $key ) {
            $old_data[ $key ] = $loan->$key ?? null;
        }

        $wpdb->update( $t_loans, $update_data, [ 'id' => $loan_id ] );

        Aura_Library_Setup::log(
            'update_loan', 'loan', $loan_id,
            $old_data,
            $update_data
        );

        wp_send_json_success( [
            'message' => __( 'Préstamo actualizado correctamente.', 'aura-business-suite' ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Cancelar un préstamo (restaura copia disponible)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_cancel(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_library_loan_delete' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para cancelar préstamos.', 'aura-business-suite' ) ] );
        }

        $loan_id = intval( $_POST['loan_id'] ?? 0 );
        if ( ! $loan_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de préstamo inválido.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';

        $loan = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t_loans} WHERE id = %d",
            $loan_id
        ) );

        if ( ! $loan ) {
            wp_send_json_error( [ 'message' => __( 'Préstamo no encontrado.', 'aura-business-suite' ) ] );
        }

        if ( in_array( $loan->status, [ 'returned', 'cancelled' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Este préstamo ya fue devuelto o cancelado.', 'aura-business-suite' ) ] );
        }

        // Restaurar copia disponible si el libro estaba prestado activamente
        $was_active = in_array( $loan->status, [ 'active', 'extended', 'overdue' ], true );

        $wpdb->update( $t_loans, [ 'status' => 'cancelled' ], [ 'id' => $loan_id ] );

        if ( $was_active ) {
            Aura_Library_Books::increment_available_copies( (int) $loan->book_id );
        }

        Aura_Library_Setup::log(
            'cancel_loan', 'loan', $loan_id,
            [ 'status' => $loan->status ],
            [ 'status' => 'cancelled' ]
        );

        wp_send_json_success( [
            'message' => __( 'Préstamo cancelado correctamente.', 'aura-business-suite' ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    public static function get_status_label( string $status ): string {
        $labels = [
            'active'    => __( 'Activo',    'aura-business-suite' ),
            'returned'  => __( 'Devuelto',  'aura-business-suite' ),
            'overdue'   => __( 'Vencido',   'aura-business-suite' ),
            'lost'      => __( 'Perdido',   'aura-business-suite' ),
            'extended'  => __( 'Extendido', 'aura-business-suite' ),
            'cancelled' => __( 'Cancelado', 'aura-business-suite' ),
        ];
        return $labels[ $status ] ?? $status;
    }
}
