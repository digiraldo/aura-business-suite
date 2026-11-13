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
            'get_list'           => 'ajax_get_list',
            'save'               => 'ajax_save',
            'delete'             => 'ajax_delete',
            'get_detail'         => 'ajax_get_detail',
            'search'             => 'ajax_search',
            'lookup_isbn'        => 'ajax_lookup_isbn',
            'search_external'    => 'ajax_search_external',
            'create_remote_scan' => 'ajax_create_remote_scan',
            'poll_remote_scan'   => 'ajax_poll_remote_scan',
        ];

        foreach ( $ajax_actions as $action => $handler ) {
            add_action( 'wp_ajax_aura_library_books_' . $action, [ __CLASS__, $handler ] );
        }

        // Endpoints públicos (autenticados por token temporal) para escáner remoto desde celular
        add_action( 'wp_ajax_aura_library_remote_scanner',        [ __CLASS__, 'ajax_render_remote_scanner' ] );
        add_action( 'wp_ajax_nopriv_aura_library_remote_scanner', [ __CLASS__, 'ajax_render_remote_scanner' ] );
        add_action( 'wp_ajax_aura_library_remote_scan_submit',        [ __CLASS__, 'ajax_remote_scan_submit' ] );
        add_action( 'wp_ajax_nopriv_aura_library_remote_scan_submit', [ __CLASS__, 'ajax_remote_scan_submit' ] );
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

        $cover_image_id = intval( $_POST['cover_image_id'] ?? 0 );
        $cover_url      = esc_url_raw( wp_unslash( $_POST['cover_url'] ?? '' ) );
        if ( ! $cover_image_id && $cover_url ) {
            $sideloaded_id = self::sideload_cover_image( $cover_url, $title );
            if ( $sideloaded_id > 0 ) {
                $cover_image_id = $sideloaded_id;
            }
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
            'cover_image_id'    => $cover_image_id ?: null,
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

    // ─────────────────────────────────────────────────────────────
    // AJAX — Búsqueda inteligente de metadatos por ISBN
    //        (Google Books API + Open Library API)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_lookup_isbn(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if (
            ! current_user_can( 'aura_library_create' ) &&
            ! current_user_can( 'aura_library_edit' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para consultar libros.', 'aura-business-suite' ) ] );
        }

        $raw_isbn   = sanitize_text_field( wp_unslash( $_POST['isbn'] ?? '' ) );
        $clean_isbn = preg_replace( '/[^0-9X]/i', '', strtoupper( $raw_isbn ) );

        if ( empty( $clean_isbn ) || ! self::validate_isbn( $clean_isbn ) ) {
            wp_send_json_error( [ 'message' => __( 'Ingresa o escanea un ISBN-10 o ISBN-13 válido.', 'aura-business-suite' ) ] );
        }

        $cache_key = 'aura_lib_isbn_v3_' . $clean_isbn;
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached['title'] ) ) {
            wp_send_json_success( $cached );
        }

        $google  = self::fetch_google_books( $clean_isbn );
        $openlib = self::fetch_open_library( $clean_isbn );

        $merged = [
            'isbn'           => $clean_isbn,
            'title'          => $google['title']          ?: ( $openlib['title'] ?? '' ),
            'subtitle'       => $google['subtitle']       ?: ( $openlib['subtitle'] ?? '' ),
            'author'         => $google['author']         ?: ( $openlib['author'] ?? '' ),
            'publisher'      => $google['publisher']      ?: ( $openlib['publisher'] ?? '' ),
            'year_published' => $google['year_published'] ?: ( $openlib['year_published'] ?? '' ),
            'pages'          => $google['pages']          ?: ( $openlib['pages'] ?? '' ),
            'language'       => $google['language']       ?: ( $openlib['language'] ?? 'Español' ),
            'dewey_number'   => $openlib['dewey_number']  ?: '',
            'category'       => $google['category']       ?: ( $openlib['category'] ?? '' ),
            'subcategory'    => $google['subcategory']    ?: ( $openlib['subcategory'] ?? '' ),
            'description'    => $google['description']    ?: ( $openlib['description'] ?? '' ),
            'keywords'       => $google['keywords']       ?: ( $openlib['keywords'] ?? '' ),
            'cover_url'      => $google['cover_url']      ?: ( $openlib['cover_url'] ?? '' ),
            'sources'        => array_values( array_filter( [
                ! empty( $google['title'] ) ? 'Google Books' : '',
                ! empty( $openlib['title'] ) ? 'Open Library' : '',
            ] ) ),
        ];

        if ( empty( $merged['title'] ) ) {
            wp_send_json_error( [
                'message' => __( 'No se encontraron datos para este ISBN en Google Books ni en Open Library.', 'aura-business-suite' ),
            ] );
        }

        // Fallback Open Library Search API para Dewey y primera oración si faltan
        if ( empty( $merged['dewey_number'] ) || empty( $merged['description'] ) ) {
            $ol_search = self::fetch_open_library_search( $clean_isbn );
            if ( empty( $merged['dewey_number'] ) && ! empty( $ol_search['dewey_number'] ) ) {
                $merged['dewey_number'] = $ol_search['dewey_number'];
            }
            if ( empty( $merged['description'] ) && ! empty( $ol_search['description'] ) ) {
                $merged['description'] = $ol_search['description'];
            }
        }

        // Fallback Google Books por Título + Autor si la ficha ISBN no trajo descripción
        if ( empty( $merged['description'] ) && ! empty( $merged['title'] ) ) {
            $desc_fallback = self::fetch_description_fallback( $merged['title'], $merged['author'] );
            if ( ! empty( $desc_fallback ) ) {
                $merged['description'] = $desc_fallback;
            }
        }

        // Clasificador Dewey inteligente (especializado en libros cristianos, liderazgo y teología)
        if ( empty( $merged['dewey_number'] ) ) {
            $merged['dewey_number'] = self::infer_dewey_number( $merged );
        }

        set_transient( $cache_key, $merged, 12 * HOUR_IN_SECONDS );
        wp_send_json_success( $merged );
    }

    /**
     * AJAX — Búsqueda externa en español por Título / Autor cuando el ISBN
     * apunta a una ficha en otro idioma.
     */
    public static function ajax_search_external(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if (
            ! current_user_can( 'aura_library_create' ) &&
            ! current_user_can( 'aura_library_edit' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para consultar libros.', 'aura-business-suite' ) ] );
        }

        $query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
        if ( mb_strlen( $query ) < 3 ) {
            wp_send_json_error( [ 'message' => __( 'Escribe al menos 3 caracteres (título o autor) para buscar.', 'aura-business-suite' ) ] );
        }

        $url      = 'https://www.googleapis.com/books/v1/volumes?q=' . rawurlencode( $query ) . '&langRestrict=es&maxResults=5';
        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

        $items = [];
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $body  = json_decode( wp_remote_retrieve_body( $response ), true );
            $items = $body['items'] ?? [];
        }

        if ( empty( $items ) ) {
            $url_any  = 'https://www.googleapis.com/books/v1/volumes?q=' . rawurlencode( $query ) . '&maxResults=5';
            $resp_any = wp_remote_get( $url_any, [ 'timeout' => 10 ] );
            if ( ! is_wp_error( $resp_any ) && wp_remote_retrieve_response_code( $resp_any ) === 200 ) {
                $body_any = json_decode( wp_remote_retrieve_body( $resp_any ), true );
                $items    = $body_any['items'] ?? [];
            }
        }

        if ( empty( $items ) ) {
            wp_send_json_error( [ 'message' => __( 'No se encontraron ediciones para esa búsqueda.', 'aura-business-suite' ) ] );
        }

        $parsed = self::pick_best_google_volume( $items, true );
        if ( empty( $parsed['title'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No se encontraron datos completos para esa búsqueda.', 'aura-business-suite' ) ] );
        }

        if ( empty( $parsed['description'] ) ) {
            $parsed['description'] = self::fetch_description_fallback( $parsed['title'], $parsed['author'] );
        }
        if ( empty( $parsed['dewey_number'] ) && ! empty( $parsed['isbn'] ) ) {
            $ol_s = self::fetch_open_library_search( $parsed['isbn'] );
            if ( ! empty( $ol_s['dewey_number'] ) ) {
                $parsed['dewey_number'] = $ol_s['dewey_number'];
            }
        }
        if ( empty( $parsed['dewey_number'] ) ) {
            $parsed['dewey_number'] = self::infer_dewey_number( $parsed );
        }

        $parsed['sources'] = [ 'Google Books (ES)' ];
        wp_send_json_success( $parsed );
    }

    /**
     * Consulta Google Books API por ISBN (priorizando registros en español).
     */
    private static function fetch_google_books( string $isbn ): array {
        $url      = 'https://www.googleapis.com/books/v1/volumes?q=isbn:' . rawurlencode( $isbn ) . '&maxResults=5';
        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['items'] ) || ! is_array( $body['items'] ) ) {
            return [];
        }

        return self::pick_best_google_volume( $body['items'], true );
    }

    /**
     * Selecciona el mejor volumen de Google Books priorizando idioma español
     * y combinando campos faltantes de otros registros coincidentes.
     */
    private static function pick_best_google_volume( array $items, bool $prefer_spanish = true ): array {
        $scored = [];
        foreach ( $items as $idx => $item ) {
            if ( empty( $item['volumeInfo'] ) ) {
                continue;
            }
            $info  = $item['volumeInfo'];
            $score = 0;
            $lang  = strtolower( substr( (string) ( $info['language'] ?? '' ), 0, 2 ) );
            if ( $prefer_spanish && $lang === 'es' ) {
                $score += 20;
            }
            if ( ! empty( $info['description'] ) ) {
                $score += 5;
            }
            if ( ! empty( $info['imageLinks'] ) ) {
                $score += 3;
            }
            if ( ! empty( $info['categories'] ) ) {
                $score += 2;
            }
            $snippet = $item['searchInfo']['textSnippet'] ?? '';
            $scored[] = [ 'score' => $score, 'idx' => $idx, 'data' => self::parse_google_volume( $info, $snippet ) ];
        }

        if ( empty( $scored ) ) {
            return [];
        }

        usort( $scored, static function ( $a, $b ) {
            if ( $a['score'] === $b['score'] ) {
                return $a['idx'] <=> $b['idx'];
            }
            return $b['score'] <=> $a['score'];
        } );

        $best = $scored[0]['data'];
        foreach ( array_slice( $scored, 1 ) as $other ) {
            foreach ( $other['data'] as $k => $v ) {
                if ( empty( $best[ $k ] ) && ! empty( $v ) ) {
                    $best[ $k ] = $v;
                }
            }
        }

        return $best;
    }

    /**
     * Normaliza un volumeInfo de Google Books a los campos del formulario.
     */
    private static function parse_google_volume( array $info, string $snippet = '' ): array {
        $year = '';
        if ( ! empty( $info['publishedDate'] ) && preg_match( '/\b(1[89]\d{2}|20\d{2})\b/', $info['publishedDate'], $m ) ) {
            $year = $m[1];
        }

        $categories  = is_array( $info['categories'] ?? null ) ? $info['categories'] : [];
        $category    = '';
        $subcategory = '';
        if ( ! empty( $categories[0] ) ) {
            $parts       = array_map( 'trim', explode( '/', $categories[0] ) );
            $category    = sanitize_text_field( $parts[0] ?? '' );
            $subcategory = sanitize_text_field( $parts[1] ?? ( $categories[1] ?? '' ) );
        }

        $cover_url = '';
        if ( ! empty( $info['imageLinks'] ) ) {
            $raw_img = $info['imageLinks']['extraLarge']
                ?? $info['imageLinks']['large']
                ?? $info['imageLinks']['medium']
                ?? $info['imageLinks']['thumbnail']
                ?? $info['imageLinks']['smallThumbnail']
                ?? '';
            if ( $raw_img ) {
                $cover_url = set_url_scheme( $raw_img, 'https' );
                $cover_url = str_replace( '&edge=curl', '', $cover_url );
            }
        }

        $isbn = '';
        if ( ! empty( $info['industryIdentifiers'] ) && is_array( $info['industryIdentifiers'] ) ) {
            foreach ( $info['industryIdentifiers'] as $id_obj ) {
                if ( ( $id_obj['type'] ?? '' ) === 'ISBN_13' && ! empty( $id_obj['identifier'] ) ) {
                    $isbn = sanitize_text_field( $id_obj['identifier'] );
                    break;
                }
                if ( ( $id_obj['type'] ?? '' ) === 'ISBN_10' && empty( $isbn ) && ! empty( $id_obj['identifier'] ) ) {
                    $isbn = sanitize_text_field( $id_obj['identifier'] );
                }
            }
        }

        $keywords = array_slice( array_unique( array_filter( array_map( 'sanitize_text_field', $categories ) ) ), 0, 6 );

        $raw_desc = ! empty( $info['description'] ) ? $info['description'] : $snippet;

        return [
            'isbn'           => $isbn,
            'title'          => sanitize_text_field( $info['title'] ?? '' ),
            'subtitle'       => sanitize_text_field( $info['subtitle'] ?? '' ),
            'author'         => ! empty( $info['authors'] ) ? sanitize_text_field( implode( ', ', (array) $info['authors'] ) ) : '',
            'publisher'      => sanitize_text_field( $info['publisher'] ?? '' ),
            'year_published' => $year,
            'pages'          => ! empty( $info['pageCount'] ) ? (string) absint( $info['pageCount'] ) : '',
            'language'       => self::map_language_code( $info['language'] ?? '' ),
            'category'       => $category,
            'subcategory'    => $subcategory,
            'description'    => sanitize_textarea_field( html_entity_decode( wp_strip_all_tags( (string) $raw_desc ), ENT_QUOTES, 'UTF-8' ) ),
            'keywords'       => implode( ', ', $keywords ),
            'cover_url'      => esc_url_raw( $cover_url ),
        ];
    }

    /**
     * Consulta Open Library Search API por ISBN para obtener Número Dewey (ddc)
     * o primera oración cuando el endpoint books no los incluye.
     */
    private static function fetch_open_library_search( string $isbn ): array {
        $url      = 'https://openlibrary.org/search.json?isbn=' . rawurlencode( $isbn ) . '&fields=ddc,first_sentence,subject&limit=1';
        $response = wp_remote_get( $url, [ 'timeout' => 8 ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $doc  = $body['docs'][0] ?? [];
        if ( empty( $doc ) ) {
            return [];
        }

        $dewey = '';
        if ( ! empty( $doc['ddc'][0] ) ) {
            $dewey = sanitize_text_field( (string) $doc['ddc'][0] );
        }

        $desc = '';
        if ( ! empty( $doc['first_sentence'] ) ) {
            $fs   = is_array( $doc['first_sentence'] ) ? ( $doc['first_sentence'][0] ?? '' ) : $doc['first_sentence'];
            $desc = sanitize_textarea_field( (string) $fs );
        }

        return [
            'dewey_number' => $dewey,
            'description'  => $desc,
        ];
    }

    /**
     * Busca la sinopsis en Google Books por Título + Autor (priorizando español)
     * cuando el registro por ISBN viene sin descripción.
     */
    private static function fetch_description_fallback( string $title, string $author = '' ): string {
        $q_parts = [ 'intitle:' . $title ];
        if ( ! empty( $author ) ) {
            $first_author = trim( explode( ',', $author )[0] );
            if ( $first_author ) {
                $q_parts[] = 'inauthor:' . $first_author;
            }
        }
        $query = implode( ' ', $q_parts );

        foreach ( [ '&langRestrict=es', '' ] as $lang_param ) {
            $url      = 'https://www.googleapis.com/books/v1/volumes?q=' . rawurlencode( $query ) . $lang_param . '&maxResults=5';
            $response = wp_remote_get( $url, [ 'timeout' => 8 ] );
            if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
                continue;
            }
            $body  = json_decode( wp_remote_retrieve_body( $response ), true );
            $items = $body['items'] ?? [];
            foreach ( $items as $item ) {
                $desc = $item['volumeInfo']['description'] ?? ( $item['searchInfo']['textSnippet'] ?? '' );
                if ( ! empty( $desc ) ) {
                    return sanitize_textarea_field( html_entity_decode( wp_strip_all_tags( (string) $desc ), ENT_QUOTES, 'UTF-8' ) );
                }
            }
        }

        return '';
    }

    /**
     * Clasificador Decimal Dewey automático basado en título, categoría y descripción,
     * optimizado para bibliotecas cristianas, teológicas y de liderazgo.
     */
    private static function infer_dewey_number( array $book ): string {
        $haystack = strtolower( remove_accents( implode( ' ', [
            $book['title']       ?? '',
            $book['subtitle']    ?? '',
            $book['category']    ?? '',
            $book['subcategory'] ?? '',
            $book['keywords']    ?? '',
            $book['description'] ?? '',
        ] ) ) );

        if ( trim( $haystack ) === '' ) {
            return '';
        }

        $rules = [
            // ── Religión / Teología / Vida Cristiana / Liderazgo Cristiano (200-299) ──
            '222.8' => [ 'nehemias', 'nehemiah', 'esdras', 'ezra' ],
            '223.2' => [ 'salmos', 'psalms' ],
            '226'   => [ 'evangelios', 'gospels', 'mateo', 'marcos', 'lucas', 'juan', 'parabolas' ],
            '227'   => [ 'epistolas', 'romanos', 'corintios', 'galatas', 'efesios', 'filipenses', 'colosenses', 'hebreos' ],
            '228'   => [ 'apocalipsis', 'revelation' ],
            '220'   => [ 'biblia', 'bible', 'estudio biblico', 'escrituras', 'antiguo testamento', 'nuevo testamento' ],
            '231.3' => [ 'espiritu santo', 'holy spirit' ],
            '232'   => [ 'jesucristo', 'jesus', 'cristologia', 'mesias' ],
            '234'   => [ 'salvacion', 'gracia', 'redencion', 'fe cristiana', 'justificacion' ],
            '236'   => [ 'escatologia', 'segunda venida', 'fin de los tiempos', 'profecia' ],
            '239'   => [ 'apologetica', 'defensa de la fe' ],
            '230'   => [ 'teologia', 'theology', 'doctrina cristiana', 'sistematica' ],
            '242'   => [ 'devocional', 'devotional', 'meditaciones diarias' ],
            '248.3' => [ 'oracion', 'prayer', 'intercesion', 'ayuno' ],
            '248.84'=> [ 'matrimonio cristiano', 'familia cristiana', 'padres cristianos', 'esposos', 'noviazgo cristiano' ],
            '248.83'=> [ 'jovenes cristianos', 'adolescentes cristianos', 'juventud cristiana' ],
            '251'   => [ 'predicacion', 'homiletica', 'sermones', 'preaching' ],
            '253.7' => [ 'discipulado', 'discipleship', 'celulas', 'grupos pequenos' ],
            '253'   => [ 'liderazgo cristiano', 'christian leadership', 'liderazgo con proposito', 'purpose driven leadership', 'pastoral', 'pastor', 'ministerio cristiano', 'christian ministry', 'ancianos', 'diaconos' ],
            '254'   => [ 'administracion eclesiastica', 'crecimiento de iglesia', 'church growth', 'iglesia con proposito' ],
            '264'   => [ 'adoracion', 'alabanza', 'worship', 'liturgia' ],
            '266'   => [ 'misiones', 'misionero', 'evangelismo', 'evangelizacion', 'gran comision' ],
            '268'   => [ 'educacion cristiana', 'escuela dominical', 'maestro cristiano' ],
            '270'   => [ 'historia de la iglesia', 'historia del cristianismo', 'reforma protestante', 'padres de la iglesia' ],
            '248.4' => [ 'vida cristiana', 'proposito de vida', 'caracter cristiano', 'christian living', 'santidad', 'mayordomia', 'proposito' ],
            '200'   => [ 'religion', 'cristianismo', 'christianity', 'iglesia', 'church', 'dios', 'god', 'fe', 'editorial vida', 'unilit', 'portavoz', 'carisma' ],

            // ── Liderazgo General / Administración / Finanzas (650-659) ──
            '658.4' => [ 'liderazgo', 'leadership', 'gerencia', 'management', 'ejecutivo', 'estrategia', 'organizacion' ],
            '658.15'=> [ 'finanzas', 'contabilidad', 'presupuesto', 'inversion' ],
            '658'   => [ 'administracion', 'negocios', 'business', 'emprendimiento' ],

            // ── Psicología / Autoayuda / Familia (150-159) ──
            '158.1' => [ 'autoayuda', 'superacion', 'habitos', 'exito personal', 'inteligencia emocional', 'self-help' ],
            '150'   => [ 'psicologia', 'psychology', 'mente', 'conducta' ],
            '100'   => [ 'filosofia', 'etica', 'logica' ],

            // ── Ciencias Sociales / Educación (300-399) ──
            '370'   => [ 'educacion', 'pedagogia', 'ensenanza', 'docencia' ],
            '330'   => [ 'economia', 'economics' ],
            '300'   => [ 'sociologia', 'sociedad', 'politica', 'derecho' ],

            // ── Informática / Idiomas / Ciencias / Artes / Literatura / Historia ──
            '005'   => [ 'programacion', 'software', 'desarrollo web', 'python', 'javascript', 'php' ],
            '004'   => [ 'informatica', 'computacion', 'tecnologia', 'computers' ],
            '400'   => [ 'gramatica', 'linguistica', 'diccionario', 'idioma', 'ortografia' ],
            '500'   => [ 'ciencia', 'fisica', 'quimica', 'biologia', 'matematica' ],
            '780'   => [ 'musica', 'canto', 'instrumentos' ],
            '700'   => [ 'arte', 'diseno', 'pintura', 'arquitectura' ],
            '800'   => [ 'novela', 'ficcion', 'literatura', 'poesia', 'cuentos', 'fiction' ],
            '920'   => [ 'biografia', 'biography', 'memorias', 'autobiografia' ],
            '900'   => [ 'historia', 'history', 'geografia' ],
        ];

        foreach ( $rules as $dewey => $terms ) {
            foreach ( $terms as $term ) {
                if ( strpos( $haystack, $term ) !== false ) {
                    return $dewey;
                }
            }
        }

        return '';
    }

    /**
     * Consulta Open Library API por ISBN (Dewey, metadatos y portada).
     */
    private static function fetch_open_library( string $isbn ): array {
        $key      = 'ISBN:' . $isbn;
        $url      = 'https://openlibrary.org/api/books?bibkeys=' . rawurlencode( $key ) . '&jscmd=data&format=json';
        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body[ $key ] ) ) {
            return [];
        }

        $info = $body[ $key ];

        $authors = [];
        if ( ! empty( $info['authors'] ) && is_array( $info['authors'] ) ) {
            foreach ( $info['authors'] as $a ) {
                if ( ! empty( $a['name'] ) ) {
                    $authors[] = sanitize_text_field( $a['name'] );
                }
            }
        }

        $publisher = '';
        if ( ! empty( $info['publishers'][0]['name'] ) ) {
            $publisher = sanitize_text_field( $info['publishers'][0]['name'] );
        }

        $year = '';
        if ( ! empty( $info['publish_date'] ) && preg_match( '/\b(1[89]\d{2}|20\d{2})\b/', $info['publish_date'], $m ) ) {
            $year = $m[1];
        }

        $dewey = '';
        if ( ! empty( $info['classifications']['dewey_decimal_class'][0] ) ) {
            $dewey = sanitize_text_field( $info['classifications']['dewey_decimal_class'][0] );
        }

        $subjects = [];
        if ( ! empty( $info['subjects'] ) && is_array( $info['subjects'] ) ) {
            foreach ( array_slice( $info['subjects'], 0, 6 ) as $s ) {
                if ( ! empty( $s['name'] ) ) {
                    $subjects[] = sanitize_text_field( $s['name'] );
                }
            }
        }

        $cover_url = '';
        if ( ! empty( $info['cover'] ) ) {
            $cover_url = $info['cover']['large'] ?? $info['cover']['medium'] ?? $info['cover']['small'] ?? '';
        }

        $description = '';
        if ( ! empty( $info['notes'] ) && is_string( $info['notes'] ) ) {
            $description = sanitize_textarea_field( wp_strip_all_tags( $info['notes'] ) );
        } elseif ( ! empty( $info['excerpts'][0]['text'] ) ) {
            $description = sanitize_textarea_field( wp_strip_all_tags( $info['excerpts'][0]['text'] ) );
        }

        return [
            'title'          => sanitize_text_field( $info['title'] ?? '' ),
            'subtitle'       => sanitize_text_field( $info['subtitle'] ?? '' ),
            'author'         => implode( ', ', $authors ),
            'publisher'      => $publisher,
            'year_published' => $year,
            'pages'          => ! empty( $info['number_of_pages'] ) ? (string) absint( $info['number_of_pages'] ) : '',
            'language'       => '',
            'dewey_number'   => $dewey,
            'category'       => $subjects[0] ?? '',
            'subcategory'    => $subjects[1] ?? '',
            'description'    => $description,
            'keywords'       => implode( ', ', $subjects ),
            'cover_url'      => esc_url_raw( $cover_url ),
        ];
    }

    /**
     * Mapea códigos ISO 639-1 al catálogo de idiomas del formulario.
     */
    private static function map_language_code( string $code ): string {
        $map = [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
            'pt' => 'Portugués',
            'de' => 'Alemán',
            'it' => 'Italiano',
        ];
        $c = strtolower( substr( trim( $code ), 0, 2 ) );
        return $map[ $c ] ?? 'Otro';
    }

    /**
     * Descarga e importa una portada externa a la Biblioteca de Medios de WordPress.
     */
    private static function sideload_cover_image( string $url, string $title ): int {
        if ( ! preg_match( '#^https?://#i', $url ) ) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $url, 15 );
        if ( is_wp_error( $tmp ) ) {
            return 0;
        }

        $mime = function_exists( 'mime_content_type' ) ? mime_content_type( $tmp ) : '';
        $ext  = 'jpg';
        if ( $mime === 'image/png' ) {
            $ext = 'png';
        } elseif ( $mime === 'image/webp' ) {
            $ext = 'webp';
        } elseif ( $mime === 'image/gif' ) {
            $ext = 'gif';
        }

        $slug       = sanitize_title( $title ?: 'libro-portada' );
        $file_array = [
            'name'     => $slug . '-' . wp_rand( 100, 999 ) . '.' . $ext,
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload( $file_array, 0, $title );
        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            return 0;
        }

        return (int) $attachment_id;
    }

    // ─────────────────────────────────────────────────────────────
    // ESCÁNER REMOTO DESDE CELULAR (Samsung Galaxy / iPhone / QR)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_create_remote_scan(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if (
            ! current_user_can( 'aura_library_create' ) &&
            ! current_user_can( 'aura_library_edit' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        try {
            $token = bin2hex( random_bytes( 16 ) );
        } catch ( \Exception $e ) {
            $token = wp_generate_password( 32, false, false );
        }

        set_transient( 'aura_lib_rscan_' . $token, [
            'status'  => 'waiting',
            'isbn'    => '',
            'user_id' => get_current_user_id(),
        ], 10 * MINUTE_IN_SECONDS );

        $scan_url = add_query_arg( [
            'action' => 'aura_library_remote_scanner',
            'token'  => $token,
        ], admin_url( 'admin-ajax.php' ) );

        wp_send_json_success( [
            'token'    => $token,
            'scan_url' => $scan_url,
            'is_https' => is_ssl(),
        ] );
    }

    public static function ajax_poll_remote_scan(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $token = sanitize_key( wp_unslash( $_POST['token'] ?? '' ) );
        if ( empty( $token ) ) {
            wp_send_json_error( [ 'status' => 'invalid' ] );
        }

        $key   = 'aura_lib_rscan_' . $token;
        $state = get_transient( $key );
        if ( ! is_array( $state ) ) {
            wp_send_json_success( [ 'status' => 'expired' ] );
        }

        if ( ! empty( $state['isbn'] ) ) {
            $isbn = $state['isbn'];
            // Reiniciar para permitir escaneos sucesivos si el usuario sigue en el modal
            $state['status'] = 'waiting';
            $state['isbn']   = '';
            set_transient( $key, $state, 10 * MINUTE_IN_SECONDS );
            wp_send_json_success( [ 'status' => 'scanned', 'isbn' => $isbn ] );
        }

        wp_send_json_success( [ 'status' => 'waiting' ] );
    }

    public static function ajax_remote_scan_submit(): void {
        $token = sanitize_key( wp_unslash( $_POST['token'] ?? '' ) );
        $raw   = sanitize_text_field( wp_unslash( $_POST['isbn'] ?? '' ) );
        $isbn  = preg_replace( '/[^0-9X]/i', '', strtoupper( $raw ) );

        $key   = 'aura_lib_rscan_' . $token;
        $state = get_transient( $key );
        if ( empty( $token ) || ! is_array( $state ) ) {
            wp_send_json_error( [ 'message' => __( 'Sesión expirada. Genera un nuevo código QR en tu computadora.', 'aura-business-suite' ) ] );
        }

        if ( empty( $isbn ) || ! self::validate_isbn( $isbn ) ) {
            wp_send_json_error( [ 'message' => __( 'Código no válido como ISBN-10 o ISBN-13.', 'aura-business-suite' ) ] );
        }

        $state['status'] = 'scanned';
        $state['isbn']   = $isbn;
        set_transient( $key, $state, 10 * MINUTE_IN_SECONDS );

        wp_send_json_success( [ 'isbn' => $isbn ] );
    }

    public static function ajax_render_remote_scanner(): void {
        $token = sanitize_key( wp_unslash( $_GET['token'] ?? '' ) );
        $state = $token ? get_transient( 'aura_lib_rscan_' . $token ) : false;

        header( 'Content-Type: text/html; charset=utf-8' );
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title><?php esc_html_e( 'Escáner Móvil — Aura Biblioteca', 'aura-business-suite' ); ?></title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; min-height: 100dvh; display: flex; flex-direction: column; }
    header { padding: 14px 16px; background: #1e293b; border-bottom: 1px solid #334155; display: flex; align-items: center; justify-content: space-between; }
    header h1 { margin: 0; font-size: 16px; font-weight: 700; }
    .wrap { flex: 1; padding: 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; max-width: 520px; width: 100%; margin: 0 auto; }
    .cam-select { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #475569; background: #1e293b; color: #fff; font-size: 15px; }
    #reader { width: 100%; border-radius: 12px; overflow: hidden; background: #000; border: 2px solid #0ea5e9; }
    .status { text-align: center; font-size: 14px; color: #cbd5e1; padding: 10px 14px; border-radius: 8px; background: #1e293b; width: 100%; }
    .status.ok { background: #065f46; color: #ecfdf5; border: 1px solid #10b981; font-weight: 600; }
    .status.err { background: #7f1d1d; color: #fef2f2; border: 1px solid #ef4444; }
    .btn { padding: 12px 18px; border: none; border-radius: 8px; background: #0ea5e9; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; width: 100%; }
</style>
</head>
<body>
<header>
    <h1>📷 <?php esc_html_e( 'Escáner de Libros — Aura', 'aura-business-suite' ); ?></h1>
</header>
<div class="wrap">
<?php if ( ! is_array( $state ) ) : ?>
    <div class="status err">
        <?php esc_html_e( 'Este enlace de escaneo ha expirado. Genera un nuevo código QR desde tu computadora.', 'aura-business-suite' ); ?>
    </div>
<?php else : ?>
    <select id="cam-select" class="cam-select" style="display:none;"></select>
    <div id="reader"></div>
    <div id="status" class="status"><?php esc_html_e( 'Iniciando cámara del teléfono…', 'aura-business-suite' ); ?></div>
    <button type="button" id="btn-retry" class="btn" style="display:none;"><?php esc_html_e( 'Escanear otro libro', 'aura-business-suite' ); ?></button>
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
    (function() {
        var token   = <?php echo wp_json_encode( $token ); ?>;
        var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
        var statusEl = document.getElementById('status');
        var selectEl = document.getElementById('cam-select');
        var retryBtn = document.getElementById('btn-retry');
        var scanner  = new Html5Qrcode('reader');
        var locked   = false;

        function beep() {
            try {
                var Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                var c = new Ctx(), o = c.createOscillator(), g = c.createGain();
                o.frequency.value = 1046.5; g.gain.value = 0.15;
                o.connect(g); g.connect(c.destination); o.start(); o.stop(c.currentTime + 0.15);
            } catch (e) {}
        }

        function startWith(cameraConfig) {
            locked = false;
            retryBtn.style.display = 'none';
            statusEl.className = 'status';
            statusEl.textContent = 'Apunta al código de barras (ISBN) del libro…';

            var cfg = {
                fps: 12,
                qrbox: function(w, h) {
                    return { width: Math.max(200, Math.floor(w * 0.85)), height: Math.max(110, Math.floor(h * 0.45)) };
                },
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.CODE_128
                ]
            };

            scanner.start(cameraConfig, cfg, function(text) {
                if (locked) return;
                var clean = String(text || '').replace(/[^0-9X]/gi, '');
                if (clean.length !== 10 && clean.length !== 13) return;
                locked = true;
                beep();
                if (navigator.vibrate) { try { navigator.vibrate(120); } catch (e) {} }
                statusEl.textContent = 'Enviando ISBN ' + clean + ' a tu computadora…';

                var fd = new FormData();
                fd.append('action', 'aura_library_remote_scan_submit');
                fd.append('token', token);
                fd.append('isbn', clean);

                fetch(ajaxUrl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            statusEl.className = 'status ok';
                            statusEl.textContent = '✅ ISBN ' + clean + ' enviado. Mira la pantalla de tu computadora.';
                            retryBtn.style.display = 'block';
                        } else {
                            locked = false;
                            statusEl.className = 'status err';
                            statusEl.textContent = (res.data && res.data.message) ? res.data.message : 'Error al enviar el código.';
                        }
                    })
                    .catch(function() {
                        locked = false;
                        statusEl.className = 'status err';
                        statusEl.textContent = 'Error de red al enviar el código.';
                    });
            }, function() {}).catch(function(err) {
                statusEl.className = 'status err';
                statusEl.textContent = 'No se pudo iniciar la cámara: ' + (err && err.message ? err.message : err) + ' (Requiere HTTPS)';
            });
        }

        Html5Qrcode.getCameras().then(function(cams) {
            if (cams && cams.length > 1) {
                cams.forEach(function(c, i) {
                    var opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.label || ('Cámara ' + (i + 1));
                    selectEl.appendChild(opt);
                });
                selectEl.style.display = 'block';
                selectEl.addEventListener('change', function() {
                    scanner.stop().then(function() { startWith(selectEl.value); }).catch(function() { startWith(selectEl.value); });
                });
            }
            startWith({ facingMode: 'environment' });
        }).catch(function() {
            startWith({ facingMode: 'environment' });
        });

        retryBtn.addEventListener('click', function() {
            locked = false;
            retryBtn.style.display = 'none';
            statusEl.className = 'status';
            statusEl.textContent = 'Listo. Apunta al siguiente código de barras…';
        });
    })();
    </script>
<?php endif; ?>
</div>
</body>
</html>
        <?php
        exit;
    }
}
