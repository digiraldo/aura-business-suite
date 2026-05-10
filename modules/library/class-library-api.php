<?php
/**
 * REST API del Módulo de Biblioteca — Fase 8
 *
 * Namespace: /wp-json/aura/v1/library/
 * Endpoints: books, loans, reservations, dashboard
 *
 * Autenticación:
 *   - Desde el panel WP: cookie + nonce X-WP-Nonce (enviado por WordPress automáticamente)
 *   - Desde integraciones externas: Application Passwords (Basic Auth)
 *
 * @package Aura_Business_Suite
 * @subpackage Library
 * @since 1.7.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Library_Api {

    const NAMESPACE = 'aura/v1';
    const BASE      = 'library';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // REGISTRO DE RUTAS
    // ─────────────────────────────────────────────────────────────

    public static function register_routes(): void {
        $ns = self::NAMESPACE;
        $b  = self::BASE;

        // ── Books ──────────────────────────────────────────────
        register_rest_route( $ns, "/{$b}/books", [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_books' ],
                'permission_callback' => [ __CLASS__, 'can_view_catalog' ],
                'args'                => [
                    'page'     => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
                    'per_page' => [ 'default' => 20, 'sanitize_callback' => 'absint' ],
                    'search'   => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
                    'status'   => [ 'default' => '', 'sanitize_callback' => 'sanitize_key' ],
                    'dewey'    => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'create_book' ],
                'permission_callback' => [ __CLASS__, 'can_create' ],
                'args'                => self::book_args(),
            ],
        ] );

        register_rest_route( $ns, "/{$b}/books/(?P<id>\d+)", [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_book' ],
                'permission_callback' => [ __CLASS__, 'can_view_catalog' ],
                'args'                => [ 'id' => [ 'validate_callback' => 'is_numeric' ] ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ __CLASS__, 'update_book' ],
                'permission_callback' => [ __CLASS__, 'can_edit' ],
                'args'                => self::book_args( false ),
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ __CLASS__, 'delete_book' ],
                'permission_callback' => [ __CLASS__, 'can_delete' ],
                'args'                => [ 'id' => [ 'validate_callback' => 'is_numeric' ] ],
            ],
        ] );

        // ── Loans ──────────────────────────────────────────────
        register_rest_route( $ns, "/{$b}/loans", [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_loans' ],
                'permission_callback' => [ __CLASS__, 'can_view_loans' ],
                'args'                => [
                    'page'     => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
                    'per_page' => [ 'default' => 20, 'sanitize_callback' => 'absint' ],
                    'status'   => [ 'default' => '', 'sanitize_callback' => 'sanitize_key' ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'create_loan' ],
                'permission_callback' => [ __CLASS__, 'can_create_loan' ],
                'args'                => [
                    'book_id'          => [ 'required' => true, 'sanitize_callback' => 'absint' ],
                    'borrower_user_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
                    'due_date'         => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                    'notes'            => [ 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ],
                ],
            ],
        ] );

        register_rest_route( $ns, "/{$b}/loans/(?P<id>\d+)", [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_loan' ],
                'permission_callback' => [ __CLASS__, 'can_view_loans_all' ],
                'args'                => [ 'id' => [ 'validate_callback' => 'is_numeric' ] ],
            ],
        ] );

        register_rest_route( $ns, "/{$b}/loans/(?P<id>\d+)/return", [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ __CLASS__, 'return_loan' ],
                'permission_callback' => [ __CLASS__, 'can_return_loan' ],
                'args'                => [
                    'id'          => [ 'validate_callback' => 'is_numeric' ],
                    'return_date' => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
                    'collect_fine'=> [ 'default' => false ],
                ],
            ],
        ] );

        register_rest_route( $ns, "/{$b}/loans/(?P<id>\d+)/extend", [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ __CLASS__, 'extend_loan' ],
                'permission_callback' => [ __CLASS__, 'can_extend_loan' ],
                'args'                => [
                    'id'       => [ 'validate_callback' => 'is_numeric' ],
                    'new_date' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ],
        ] );

        // ── Reservations ───────────────────────────────────────
        register_rest_route( $ns, "/{$b}/reservations", [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_reservations' ],
                'permission_callback' => [ __CLASS__, 'can_view_loans_all' ],
                'args'                => [
                    'page'   => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
                    'status' => [ 'default' => '', 'sanitize_callback' => 'sanitize_key' ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'create_reservation' ],
                'permission_callback' => [ __CLASS__, 'can_view_loans_own' ],
                'args'                => [
                    'book_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
                    'notes'   => [ 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ],
                ],
            ],
        ] );

        // ── Dashboard ─────────────────────────────────────────
        register_rest_route( $ns, "/{$b}/dashboard", [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_dashboard' ],
                'permission_callback' => [ __CLASS__, 'can_access' ],
            ],
        ] );

        // ── Reports summary ───────────────────────────────────
        register_rest_route( $ns, "/{$b}/reports/summary", [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_reports_summary' ],
                'permission_callback' => [ __CLASS__, 'can_reports' ],
            ],
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // CALLBACKS — Books
    // ─────────────────────────────────────────────────────────────

    public static function get_books( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $table    = $wpdb->prefix . 'aura_library_books';
        $page     = $request->get_param( 'page' );
        $per_page = min( (int) $request->get_param( 'per_page' ), 100 );
        $offset   = ( $page - 1 ) * $per_page;
        $search   = $request->get_param( 'search' );
        $status   = $request->get_param( 'status' );
        $dewey    = $request->get_param( 'dewey' );

        $where  = 'WHERE deleted_at IS NULL';
        $params = [];

        if ( $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where   .= ' AND (title LIKE %s OR author LIKE %s OR isbn LIKE %s OR dewey_number LIKE %s)';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if ( $status ) {
            $where   .= ' AND status = %s';
            $params[] = $status;
        }
        if ( $dewey ) {
            $where   .= ' AND dewey_number LIKE %s';
            $params[] = $wpdb->esc_like( $dewey ) . '%';
        }

        $params[] = $per_page;
        $params[] = $offset;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            empty( $params )
                ? "SELECT * FROM {$table} {$where} ORDER BY title ASC LIMIT {$per_page} OFFSET {$offset}"
                : $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY title ASC LIMIT %d OFFSET %d", ...$params )
        );
        $total_params = array_slice( $params, 0, -2 );
        $total = (int) $wpdb->get_var(
            empty( $total_params )
                ? "SELECT COUNT(*) FROM {$table} {$where}"
                : $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", ...$total_params )
        );
        // phpcs:enable

        $response = new WP_REST_Response( array_values( $rows ?? [] ) );
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', max( 1, (int) ceil( $total / $per_page ) ) );
        return $response;
    }

    public static function get_book( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_books';
        $id    = absint( $request->get_param( 'id' ) );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id ) ); // phpcs:ignore
        if ( ! $row ) {
            return new WP_REST_Response( [ 'message' => __( 'Libro no encontrado.', 'aura-business-suite' ) ], 404 );
        }
        return new WP_REST_Response( $row );
    }

    public static function create_book( WP_REST_Request $request ): WP_REST_Response {
        if ( ! class_exists( 'Aura_Library_Books' ) ) {
            return new WP_REST_Response( [ 'message' => __( 'Módulo no disponible.', 'aura-business-suite' ) ], 503 );
        }

        // Reutilizar la lógica AJAX existente mapeando parámetros REST → $_POST
        $_POST = array_merge( $_POST, $request->get_params() );
        $_POST['nonce'] = wp_create_nonce( 'aura_library_nonce' );

        ob_start();
        Aura_Library_Books::ajax_create();
        $output = ob_get_clean();
        $data   = json_decode( $output, true );

        if ( empty( $data['success'] ) ) {
            return new WP_REST_Response( $data['data'] ?? [], 400 );
        }
        return new WP_REST_Response( $data['data'] ?? [], 201 );
    }

    public static function update_book( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_books';
        $id    = absint( $request->get_param( 'id' ) );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id ) ); // phpcs:ignore
        if ( ! $row ) {
            return new WP_REST_Response( [ 'message' => __( 'Libro no encontrado.', 'aura-business-suite' ) ], 404 );
        }

        $_POST = array_merge( $_POST, $request->get_params() );
        $_POST['id']    = $id;
        $_POST['nonce'] = wp_create_nonce( 'aura_library_nonce' );

        ob_start();
        Aura_Library_Books::ajax_update();
        $output = ob_get_clean();
        $data   = json_decode( $output, true );

        if ( empty( $data['success'] ) ) {
            return new WP_REST_Response( $data['data'] ?? [], 400 );
        }
        return new WP_REST_Response( $data['data'] ?? [] );
    }

    public static function delete_book( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_books';
        $id    = absint( $request->get_param( 'id' ) );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id ) ); // phpcs:ignore
        if ( ! $row ) {
            return new WP_REST_Response( [ 'message' => __( 'Libro no encontrado.', 'aura-business-suite' ) ], 404 );
        }

        $wpdb->update(
            $table,
            [ 'deleted_at' => current_time( 'mysql' ) ],
            [ 'id' => $id ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( class_exists( 'Aura_Library_Setup' ) ) {
            Aura_Library_Setup::log( 'delete_book', 'book', $id );
        }

        return new WP_REST_Response( null, 204 );
    }

    // ─────────────────────────────────────────────────────────────
    // CALLBACKS — Loans
    // ─────────────────────────────────────────────────────────────

    public static function get_loans( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $tl       = $wpdb->prefix . 'aura_library_loans';
        $tb       = $wpdb->prefix . 'aura_library_books';
        $can_all  = current_user_can( 'aura_library_view_loans_all' ) || current_user_can( 'manage_options' );
        $page     = $request->get_param( 'page' );
        $per_page = min( (int) $request->get_param( 'per_page' ), 100 );
        $offset   = ( $page - 1 ) * $per_page;
        $status   = $request->get_param( 'status' );

        $where  = 'WHERE 1=1';
        $params = [];

        if ( ! $can_all ) {
            $where   .= ' AND l.borrower_user_id = %d';
            $params[] = get_current_user_id();
        }
        if ( $status ) {
            $where   .= ' AND l.status = %s';
            $params[] = $status;
        }

        $params[] = $per_page;
        $params[] = $offset;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, b.title AS book_title
                 FROM {$tl} l
                 LEFT JOIN {$tb} b ON b.id = l.book_id
                 {$where}
                 ORDER BY l.created_at DESC
                 LIMIT %d OFFSET %d",
                ...$params
            )
        );
        $count_params = array_slice( $params, 0, -2 );
        $total = (int) $wpdb->get_var(
            empty( $count_params )
                ? "SELECT COUNT(*) FROM {$tl} l {$where}"
                : $wpdb->prepare( "SELECT COUNT(*) FROM {$tl} l {$where}", ...$count_params )
        );
        // phpcs:enable

        $response = new WP_REST_Response( array_values( $rows ?? [] ) );
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', max( 1, (int) ceil( $total / $per_page ) ) );
        return $response;
    }

    public static function get_loan( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $tl  = $wpdb->prefix . 'aura_library_loans';
        $tb  = $wpdb->prefix . 'aura_library_books';
        $id  = absint( $request->get_param( 'id' ) );
        $row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            "SELECT l.*, b.title AS book_title
             FROM {$tl} l
             LEFT JOIN {$tb} b ON b.id = l.book_id
             WHERE l.id = %d", $id
        ) );
        if ( ! $row ) {
            return new WP_REST_Response( [ 'message' => __( 'Préstamo no encontrado.', 'aura-business-suite' ) ], 404 );
        }
        return new WP_REST_Response( $row );
    }

    public static function create_loan( WP_REST_Request $request ): WP_REST_Response {
        $_POST = array_merge( $_POST, $request->get_params() );
        $_POST['nonce'] = wp_create_nonce( 'aura_library_nonce' );

        ob_start();
        Aura_Library_Loans::ajax_create();
        $output = ob_get_clean();
        $data   = json_decode( $output, true );

        if ( empty( $data['success'] ) ) {
            return new WP_REST_Response( $data['data'] ?? [], 400 );
        }
        return new WP_REST_Response( $data['data'] ?? [], 201 );
    }

    public static function return_loan( WP_REST_Request $request ): WP_REST_Response {
        $_POST = array_merge( $_POST, $request->get_params() );
        $_POST['loan_id']      = absint( $request->get_param( 'id' ) );
        $_POST['return_date']  = sanitize_text_field( $request->get_param( 'return_date' ) ?: gmdate( 'Y-m-d' ) );
        $_POST['collect_fine'] = $request->get_param( 'collect_fine' ) ? '1' : '0';
        $_POST['nonce']        = wp_create_nonce( 'aura_library_nonce' );

        ob_start();
        Aura_Library_Loans::ajax_return_book();
        $output = ob_get_clean();
        $data   = json_decode( $output, true );

        if ( empty( $data['success'] ) ) {
            return new WP_REST_Response( $data['data'] ?? [], 400 );
        }
        return new WP_REST_Response( $data['data'] ?? [] );
    }

    public static function extend_loan( WP_REST_Request $request ): WP_REST_Response {
        $_POST['loan_id']  = absint( $request->get_param( 'id' ) );
        $_POST['new_date'] = sanitize_text_field( $request->get_param( 'new_date' ) );
        $_POST['nonce']    = wp_create_nonce( 'aura_library_nonce' );

        ob_start();
        Aura_Library_Loans::ajax_extend();
        $output = ob_get_clean();
        $data   = json_decode( $output, true );

        if ( empty( $data['success'] ) ) {
            return new WP_REST_Response( $data['data'] ?? [], 400 );
        }
        return new WP_REST_Response( $data['data'] ?? [] );
    }

    // ─────────────────────────────────────────────────────────────
    // CALLBACKS — Reservations
    // ─────────────────────────────────────────────────────────────

    public static function get_reservations( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $tr       = $wpdb->prefix . 'aura_library_reservations';
        $tb       = $wpdb->prefix . 'aura_library_books';
        $page     = $request->get_param( 'page' );
        $per_page = 20;
        $offset   = ( $page - 1 ) * $per_page;
        $status   = $request->get_param( 'status' );

        $where  = 'WHERE 1=1';
        $params = [];

        if ( $status ) {
            $where   .= ' AND r.status = %s';
            $params[] = $status;
        }

        $params[] = $per_page;
        $params[] = $offset;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, b.title AS book_title
                 FROM {$tr} r
                 LEFT JOIN {$tb} b ON b.id = r.book_id
                 {$where}
                 ORDER BY r.created_at DESC
                 LIMIT %d OFFSET %d",
                ...$params
            )
        );
        // phpcs:enable

        return new WP_REST_Response( array_values( $rows ?? [] ) );
    }

    public static function create_reservation( WP_REST_Request $request ): WP_REST_Response {
        $_POST['book_id'] = absint( $request->get_param( 'book_id' ) );
        $_POST['notes']   = sanitize_textarea_field( $request->get_param( 'notes' ) );
        $_POST['nonce']   = wp_create_nonce( 'aura_library_nonce' );

        ob_start();
        Aura_Library_Reservations::ajax_create();
        $output = ob_get_clean();
        $data   = json_decode( $output, true );

        if ( empty( $data['success'] ) ) {
            return new WP_REST_Response( $data['data'] ?? [], 400 );
        }
        return new WP_REST_Response( $data['data'] ?? [], 201 );
    }

    // ─────────────────────────────────────────────────────────────
    // CALLBACKS — Dashboard & Reports
    // ─────────────────────────────────────────────────────────────

    public static function get_dashboard( WP_REST_Request $request ): WP_REST_Response {
        if ( ! class_exists( 'Aura_Library_Reports' ) ) {
            return new WP_REST_Response( [ 'message' => __( 'Módulo no disponible.', 'aura-business-suite' ) ], 503 );
        }
        return new WP_REST_Response( Aura_Library_Reports::get_kpis() );
    }

    public static function get_reports_summary( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $tl   = $wpdb->prefix . 'aura_library_loans';
        $tb   = $wpdb->prefix . 'aura_library_books';
        $tr   = $wpdb->prefix . 'aura_library_reservations';
        $today = gmdate( 'Y-m-d' );

        $summary = [
            'total_books'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tb} WHERE deleted_at IS NULL" ), // phpcs:ignore
            'active_loans'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tl} WHERE status IN ('active','extended','overdue')" ) ), // phpcs:ignore
            'overdue_loans'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tl} WHERE status IN ('active','extended','overdue') AND due_date < %s", $today ) ), // phpcs:ignore
            'pending_reserves'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tr} WHERE status = 'pending'" ) ), // phpcs:ignore
        ];

        return new WP_REST_Response( $summary );
    }

    // ─────────────────────────────────────────────────────────────
    // PERMISSION CALLBACKS
    // ─────────────────────────────────────────────────────────────

    public static function can_access(): bool {
        return current_user_can( 'aura_library_access' ) || current_user_can( 'manage_options' );
    }
    public static function can_view_catalog(): bool {
        return current_user_can( 'aura_library_view_catalog' ) || current_user_can( 'manage_options' );
    }
    public static function can_create(): bool {
        return current_user_can( 'aura_library_create' ) || current_user_can( 'manage_options' );
    }
    public static function can_edit(): bool {
        return current_user_can( 'aura_library_edit' ) || current_user_can( 'manage_options' );
    }
    public static function can_delete(): bool {
        return current_user_can( 'aura_library_delete' ) || current_user_can( 'manage_options' );
    }
    public static function can_view_loans(): bool {
        return current_user_can( 'aura_library_view_loans_all' )
            || current_user_can( 'aura_library_view_loans_own' )
            || current_user_can( 'manage_options' );
    }
    public static function can_view_loans_all(): bool {
        return current_user_can( 'aura_library_view_loans_all' ) || current_user_can( 'manage_options' );
    }
    public static function can_view_loans_own(): bool {
        return current_user_can( 'aura_library_view_loans_own' )
            || current_user_can( 'aura_library_view_loans_all' )
            || current_user_can( 'manage_options' );
    }
    public static function can_create_loan(): bool {
        return current_user_can( 'aura_library_loan_create' ) || current_user_can( 'manage_options' );
    }
    public static function can_return_loan(): bool {
        return current_user_can( 'aura_library_loan_return' ) || current_user_can( 'manage_options' );
    }
    public static function can_extend_loan(): bool {
        return current_user_can( 'aura_library_loan_extend' ) || current_user_can( 'manage_options' );
    }
    public static function can_reports(): bool {
        return current_user_can( 'aura_library_reports' ) || current_user_can( 'manage_options' );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS — Definición de args para Books
    // ─────────────────────────────────────────────────────────────

    private static function book_args( bool $required = true ): array {
        return [
            'title'             => [ 'required' => $required, 'sanitize_callback' => 'sanitize_text_field' ],
            'author'            => [ 'required' => $required, 'sanitize_callback' => 'sanitize_text_field' ],
            'isbn'              => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
            'dewey_number'      => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
            'publisher'         => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
            'publish_year'      => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
            'language'          => [ 'default' => 'es', 'sanitize_callback' => 'sanitize_key' ],
            'total_copies'      => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
            'physical_location' => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
            'shelf_code'        => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
            'description'       => [ 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ],
            'cover_url'         => [ 'default' => '', 'sanitize_callback' => 'esc_url_raw' ],
            'status'            => [ 'default' => 'available', 'sanitize_callback' => 'sanitize_key' ],
        ];
    }
}
