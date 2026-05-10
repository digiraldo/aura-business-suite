<?php
/**
 * Catálogo de Libros — Fase 2
 *
 * CRUD completo de libros: listado paginado con filtros, formulario modal de
 * alta/edición, soft-delete, búsqueda por título/autor/ISBN/Dewey y auditoría.
 *
 * @package Aura_Business_Suite
 * @subpackage Library
 * @since 1.7.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Library_Books {

    const NONCE = 'aura_library_nonce';

    // ─────────────────────────────────────────────────────────────
    // INIT — registrar acciones AJAX
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        $ajax_actions = [
            'get_list'   => 'ajax_get_list',
            'save'       => 'ajax_save',
            'delete'     => 'ajax_delete',
            'get_detail' => 'ajax_get_detail',
            'search'     => 'ajax_search',
        ];

        foreach ( $ajax_actions as $action => $handler ) {
            add_action( 'wp_ajax_aura_library_books_' . $action, [ __CLASS__, $handler ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER — páginas admin
    // ─────────────────────────────────────────────────────────────

    public static function render_list(): void {
        if ( ! current_user_can( 'aura_library_view_catalog' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'aura-business-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/library/books-list.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Listado paginado con filtros
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_list(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_library_view_catalog' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_books';

        $page     = max( 1, intval( $_POST['page']     ?? 1 ) );
        $per_page = min( 100, max( 10, intval( $_POST['per_page'] ?? 20 ) ) );
        $offset   = ( $page - 1 ) * $per_page;
        $search   = sanitize_text_field( $_POST['search']   ?? '' );
        $dewey    = sanitize_text_field( $_POST['dewey']    ?? '' );
        $category = sanitize_text_field( $_POST['category'] ?? '' );
        $status   = sanitize_text_field( $_POST['status']   ?? '' );
        $area_id  = intval( $_POST['area_id'] ?? 0 );
        $sort_by  = in_array( $_POST['sort_by'] ?? '', [ 'title', 'author', 'dewey_number', 'available_copies', 'created_at' ] )
                    ? sanitize_text_field( $_POST['sort_by'] ) : 'title';
        $sort_dir = ( strtoupper( $_POST['sort_dir'] ?? 'ASC' ) === 'DESC' ) ? 'DESC' : 'ASC';

        $where  = [ 'deleted_at IS NULL' ];
        $params = [];

        if ( $search ) {
            $where[] = '(title LIKE %s OR author LIKE %s OR isbn LIKE %s OR keywords LIKE %s)';
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            array_push( $params, $like, $like, $like, $like );
        }

        // Filtro Dewey por prefijo (ej "200" → libros 200-299)
        if ( $dewey ) {
            $dewey_prefix = preg_replace( '/[^0-9]/', '', $dewey );
            if ( $dewey_prefix !== '' ) {
                $where[] = 'dewey_number LIKE %s';
                $params[] = $wpdb->esc_like( $dewey_prefix ) . '%';
            }
        }

        if ( $category ) {
            $where[] = 'category = %s';
            $params[] = $category;
        }

        $status_allowed = [ 'available', 'unavailable', 'reference_only', 'lost', 'withdrawn' ];
        if ( $status && in_array( $status, $status_allowed ) ) {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        if ( $area_id > 0 ) {
            $where[] = 'area_id = %d';
            $params[] = $area_id;
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
        $total     = $params
            ? intval( $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) )
            : intval( $wpdb->get_var( $count_sql ) );

        $data_sql    = "SELECT * FROM {$table} {$where_sql} ORDER BY {$sort_by} {$sort_dir} LIMIT %d OFFSET %d";
        $data_params = array_merge( $params, [ $per_page, $offset ] );
        $rows        = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$data_params ) );

        foreach ( $rows as &$row ) {
            $row->status_label    = self::get_status_label( $row->status );
            $row->copies_badge    = self::get_copies_badge_class( $row->available_copies, $row->total_copies );

            // Estado visual calculado: refleja la disponibilidad real de copias
            if ( $row->status === 'available' && (int) $row->available_copies === 0 ) {
                $row->display_status       = 'on_loan';
                $row->display_status_label = __( 'En Préstamo', 'aura-business-suite' );
            } else {
                $row->display_status       = $row->status;
                $row->display_status_label = $row->status_label;
            }
            $row->cover_thumb_url = $row->cover_image_id
                ? wp_get_attachment_image_url( $row->cover_image_id, 'thumbnail' )
                : '';
            $row->cover_full_url  = $row->cover_image_id
                ? wp_get_attachment_image_url( $row->cover_image_id, 'medium' )
                : '';
            $row->can_edit   = current_user_can( 'aura_library_edit' )   || current_user_can( 'manage_options' );
            $row->can_delete = current_user_can( 'aura_library_delete' ) || current_user_can( 'manage_options' );
            $row->can_loan   = current_user_can( 'aura_library_loan_create' ) || current_user_can( 'manage_options' );
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
    // AJAX — Guardar libro (crear o actualizar)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $id     = intval( $_POST['id'] ?? 0 );
        $is_new = $id === 0;

        if ( $is_new && ! current_user_can( 'aura_library_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para agregar libros.', 'aura-business-suite' ) ] );
        }
        if ( ! $is_new && ! current_user_can( 'aura_library_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para editar libros.', 'aura-business-suite' ) ] );
        }

        $title = sanitize_text_field( $_POST['title'] ?? '' );
        if ( empty( $title ) ) {
            wp_send_json_error( [ 'message' => __( 'El título del libro es obligatorio.', 'aura-business-suite' ) ] );
        }

        $author = sanitize_text_field( $_POST['author'] ?? '' );
        if ( empty( $author ) ) {
            wp_send_json_error( [ 'message' => __( 'El autor es obligatorio.', 'aura-business-suite' ) ] );
        }

        // Validar ISBN si proporcionado
        $isbn = sanitize_text_field( $_POST['isbn'] ?? '' );
        if ( $isbn && ! self::validate_isbn( $isbn ) ) {
            wp_send_json_error( [ 'message' => __( 'El ISBN proporcionado no es válido (use formato ISBN-10 o ISBN-13).', 'aura-business-suite' ) ] );
        }

        $total_copies = max( 1, intval( $_POST['total_copies'] ?? 1 ) );

        $status_allowed = [ 'available', 'unavailable', 'reference_only', 'lost', 'withdrawn' ];
        $lang_allowed   = [ 'Español', 'Inglés', 'Francés', 'Portugués', 'Alemán', 'Italiano', 'Otro' ];

        $year = intval( $_POST['year_published'] ?? 0 );
        if ( $year && ( $year < 1800 || $year > (int) gmdate( 'Y' ) + 1 ) ) {
            $year = null;
        } else {
            $year = $year ?: null;
        }

        $data = [
            'dewey_number'      => sanitize_text_field( $_POST['dewey_number']      ?? '' ),
            'title'             => $title,
            'subtitle'          => sanitize_text_field( $_POST['subtitle']          ?? '' ) ?: null,
            'author'            => $author,
            'isbn'              => $isbn ?: null,
            'publisher'         => sanitize_text_field( $_POST['publisher']         ?? '' ) ?: null,
            'year_published'    => $year,
            'edition'           => sanitize_text_field( $_POST['edition']           ?? '' ) ?: null,
            'language'          => in_array( $_POST['language'] ?? '', $lang_allowed ) ? $_POST['language'] : 'Español',
            'pages'             => intval( $_POST['pages'] ?? 0 ) ?: null,
            'category'          => sanitize_text_field( $_POST['category']          ?? '' ) ?: null,
            'subcategory'       => sanitize_text_field( $_POST['subcategory']       ?? '' ) ?: null,
            'physical_location' => sanitize_text_field( $_POST['physical_location'] ?? '' ) ?: null,
            'shelf_code'        => sanitize_text_field( $_POST['shelf_code']        ?? '' ) ?: null,
            'total_copies'      => $total_copies,
            'cover_image_id'    => intval( $_POST['cover_image_id'] ?? 0 ) ?: null,
            'description'       => sanitize_textarea_field( $_POST['description']  ?? '' ) ?: null,
            'keywords'          => sanitize_text_field( $_POST['keywords']          ?? '' ) ?: null,
            'area_id'           => intval( $_POST['area_id'] ?? 0 ) ?: null,
            'status'            => in_array( $_POST['status'] ?? '', $status_allowed ) ? $_POST['status'] : 'available',
        ];

        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_books';

        if ( $is_new ) {
            $data['created_by']      = get_current_user_id();
            $data['available_copies'] = $total_copies;

            $result = $wpdb->insert( $table, $data );
            if ( $result === false ) {
                wp_send_json_error( [ 'message' => __( 'Error al guardar el libro.', 'aura-business-suite' ) ] );
            }

            $id = $wpdb->insert_id;

            Aura_Library_Setup::log( 'create_book', 'book', $id, [], $data );

            wp_send_json_success( [
                'id'      => $id,
                'message' => __( 'Libro registrado correctamente.', 'aura-business-suite' ),
            ] );

        } else {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, total_copies, available_copies FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id
            ) );
            if ( ! $existing ) {
                wp_send_json_error( [ 'message' => __( 'Libro no encontrado.', 'aura-business-suite' ) ] );
            }

            // Ajustar available_copies si cambió total_copies
            $diff = $total_copies - (int) $existing->total_copies;
            if ( $diff !== 0 ) {
                $data['available_copies'] = max( 0, (int) $existing->available_copies + $diff );
            }

            $old_data = (array) $existing;
            $result   = $wpdb->update( $table, $data, [ 'id' => $id ] );
            if ( $result === false ) {
                wp_send_json_error( [ 'message' => __( 'Error al actualizar el libro.', 'aura-business-suite' ) ] );
            }

            Aura_Library_Setup::log( 'update_book', 'book', $id, $old_data, $data );

            wp_send_json_success( [
                'id'      => $id,
                'message' => __( 'Libro actualizado correctamente.', 'aura-business-suite' ),
            ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Soft delete
    // ─────────────────────────────────────────────────────────────

    public static function ajax_delete(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_library_delete' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $table       = $wpdb->prefix . 'aura_library_books';
        $loans_table = $wpdb->prefix . 'aura_library_loans';

        // Verificar préstamos activos
        $active = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$loans_table} WHERE book_id = %d AND status IN ('active','overdue','extended')", $id
        ) );

        if ( $active > 0 ) {
            wp_send_json_error( [ 'message' => __( 'No se puede eliminar un libro con préstamos activos.', 'aura-business-suite' ) ] );
        }

        $result = $wpdb->update( $table, [ 'deleted_at' => current_time( 'mysql' ) ], [ 'id' => $id ] );
        if ( $result === false ) {
            wp_send_json_error( [ 'message' => __( 'Error al eliminar el libro.', 'aura-business-suite' ) ] );
        }

        Aura_Library_Setup::log( 'delete_book', 'book', $id );

        wp_send_json_success( [ 'message' => __( 'Libro eliminado correctamente.', 'aura-business-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Detalle completo
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_detail(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_library_view_catalog' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $table       = $wpdb->prefix . 'aura_library_books';
        $loans_table = $wpdb->prefix . 'aura_library_loans';

        $book = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id
        ) );

        if ( ! $book ) {
            wp_send_json_error( [ 'message' => __( 'Libro no encontrado.', 'aura-business-suite' ) ] );
        }

        $book->status_label    = self::get_status_label( $book->status );
        $book->cover_full_url  = $book->cover_image_id
            ? wp_get_attachment_image_url( $book->cover_image_id, 'large' ) : '';
        $book->cover_thumb_url = $book->cover_image_id
            ? wp_get_attachment_image_url( $book->cover_image_id, 'thumbnail' ) : '';

        // Historial de préstamos (últimos 20)
        $loans = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$loans_table} WHERE book_id = %d ORDER BY created_at DESC LIMIT 20", $id
        ) );

        foreach ( $loans as &$loan ) {
            $user               = get_user_by( 'id', $loan->borrower_user_id );
            $loan->borrower     = $user ? $user->display_name : '—';
            $loan->status_label = self::get_loan_status_label( $loan->status );
            $loan->is_overdue   = ( $loan->status === 'active' || $loan->status === 'extended' )
                && strtotime( $loan->due_date ) < time();
        }
        unset( $loan );

        wp_send_json_success( [
            'book'  => $book,
            'loans' => $loans,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Búsqueda rápida (autocomplete para formulario préstamo)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_search(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_library_view_catalog' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        $q = sanitize_text_field( $_POST['q'] ?? '' );
        if ( strlen( $q ) < 2 ) {
            wp_send_json_success( [] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_books';
        $like  = '%' . $wpdb->esc_like( $q ) . '%';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, author, dewey_number, available_copies, total_copies
             FROM {$table}
             WHERE deleted_at IS NULL AND (title LIKE %s OR author LIKE %s OR dewey_number LIKE %s OR isbn LIKE %s)
             ORDER BY title ASC LIMIT 15",
            $like, $like, $like, $like
        ) );

        wp_send_json_success( $rows );
    }

    // ─────────────────────────────────────────────────────────────
    // MÉTODOS PÚBLICOS — usados por otros módulos
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener libro por ID (sin soft-deleted).
     */
    public static function get_by_id( int $id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_books';
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id
        ) ) ?: null;
    }

    /**
     * Decrementar copias disponibles al crear un préstamo.
     */
    public static function decrement_available_copies( int $book_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_books';
        $result = $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET available_copies = available_copies - 1
             WHERE id = %d AND available_copies > 0 AND deleted_at IS NULL",
            $book_id
        ) );
        return $result > 0;
    }

    /**
     * Incrementar copias disponibles al registrar devolución.
     */
    public static function increment_available_copies( int $book_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_books';
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table}
             SET available_copies = LEAST( available_copies + 1, total_copies )
             WHERE id = %d AND deleted_at IS NULL",
            $book_id
        ) );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    public static function get_status_label( string $status ): string {
        $labels = [
            'available'      => __( 'Disponible',    'aura-business-suite' ),
            'unavailable'    => __( 'Sin stock',     'aura-business-suite' ),
            'reference_only' => __( 'Solo consulta', 'aura-business-suite' ),
            'lost'           => __( 'Perdido',       'aura-business-suite' ),
            'withdrawn'      => __( 'Retirado',      'aura-business-suite' ),
        ];
        return $labels[ $status ] ?? $status;
    }

    public static function get_loan_status_label( string $status ): string {
        $labels = [
            'active'   => __( 'Activo',    'aura-business-suite' ),
            'returned' => __( 'Devuelto',  'aura-business-suite' ),
            'overdue'  => __( 'Vencido',   'aura-business-suite' ),
            'lost'     => __( 'Perdido',   'aura-business-suite' ),
            'extended' => __( 'Extendido', 'aura-business-suite' ),
        ];
        return $labels[ $status ] ?? $status;
    }

    public static function get_copies_badge_class( int $available, int $total ): string {
        if ( $available === 0 ) return 'badge-red';
        if ( $available <= max( 1, (int) ceil( $total * 0.25 ) ) ) return 'badge-yellow';
        return 'badge-green';
    }

    /**
     * Validar ISBN-10 o ISBN-13.
     */
    public static function validate_isbn( string $isbn ): bool {
        $clean = preg_replace( '/[^0-9X]/i', '', $isbn );

        if ( strlen( $clean ) === 10 ) {
            $sum = 0;
            for ( $i = 0; $i < 9; $i++ ) {
                $sum += (10 - $i) * intval( $clean[ $i ] );
            }
            $last = strtoupper( $clean[9] ) === 'X' ? 10 : intval( $clean[9] );
            $sum += $last;
            return $sum % 11 === 0;
        }

        if ( strlen( $clean ) === 13 ) {
            $sum = 0;
            for ( $i = 0; $i < 12; $i++ ) {
                $sum += intval( $clean[ $i ] ) * ( $i % 2 === 0 ? 1 : 3 );
            }
            $check = ( 10 - ( $sum % 10 ) ) % 10;
            return $check === intval( $clean[12] );
        }

        return false;
    }
}
