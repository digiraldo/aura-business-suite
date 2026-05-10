<?php
/**
 * CRUD de Cursos y Programas — Fase 2
 *
 * Responsabilidades:
 *  - Registrar hooks AJAX para crear, editar, listar y archivar cursos
 *  - Cargar el template de listado con modal de creación/edición embebido
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Students_Courses {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_students_save_course',      [ __CLASS__, 'ajax_save_course' ] );
        add_action( 'wp_ajax_aura_students_delete_course',    [ __CLASS__, 'ajax_delete_course' ] );
        add_action( 'wp_ajax_aura_students_get_course',       [ __CLASS__, 'ajax_get_course' ] );
        add_action( 'wp_ajax_aura_students_list_courses',     [ __CLASS__, 'ajax_list_courses' ] );
        add_action( 'wp_ajax_aura_students_get_areas',        [ __CLASS__, 'ajax_get_areas' ] );
        add_action( 'wp_ajax_aura_students_get_instructors',  [ __CLASS__, 'ajax_get_instructors' ] );
        add_action( 'wp_ajax_aura_students_get_finance_cats', [ __CLASS__, 'ajax_get_finance_cats' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────

    public static function render_list(): void {
        if ( ! current_user_can( 'aura_students_courses_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
        }
        require_once AURA_PLUGIN_DIR . 'templates/students/course-list.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: GUARDAR CURSO (CREAR / EDITAR)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save_course(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_courses_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_student_courses';

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        // Sanitize inputs
        $name           = isset( $_POST['name'] )           ? sanitize_text_field( $_POST['name'] )           : '';
        $description    = isset( $_POST['description'] )    ? sanitize_textarea_field( $_POST['description'] ) : '';
        $area_id        = isset( $_POST['area_id'] )        ? absint( $_POST['area_id'] )                      : 0;
        $instructor_id  = isset( $_POST['instructor_id'] )  ? absint( $_POST['instructor_id'] )                : 0;
        $duration_weeks = isset( $_POST['duration_weeks'] ) ? absint( $_POST['duration_weeks'] )               : 0;
        $max_students   = isset( $_POST['max_students'] )   ? absint( $_POST['max_students'] )                 : 0;
        $base_cost      = isset( $_POST['base_cost'] )      ? (float) $_POST['base_cost']                      : 0.00;
        $currency       = isset( $_POST['currency'] )       ? sanitize_text_field( $_POST['currency'] )        : 'USD';
        $status         = isset( $_POST['status'] )         ? sanitize_text_field( $_POST['status'] )          : 'active';
        $start_date     = isset( $_POST['start_date'] )     ? sanitize_text_field( $_POST['start_date'] )      : '';
        $end_date       = isset( $_POST['end_date'] )       ? sanitize_text_field( $_POST['end_date'] )        : '';
        $finance_cat_id = isset( $_POST['finance_cat_id'] ) ? absint( $_POST['finance_cat_id'] )               : 0;

        // Validation
        if ( empty( $name ) ) {
            wp_send_json_error( [ 'message' => __( 'El nombre del curso es obligatorio.', 'aura-suite' ) ] );
        }

        $valid_statuses = [ 'active', 'inactive', 'archived' ];
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            $status = 'active';
        }

        $valid_currencies = [ 'USD', 'EUR', 'COP', 'MXN', 'PEN', 'ARS', 'CLP', 'BRL', 'GTQ', 'HNL', 'NIO', 'DOP', 'CRC', 'BOB' ];
        if ( ! in_array( $currency, $valid_currencies, true ) ) {
            $currency = 'USD';
        }

        if ( $base_cost < 0 ) {
            $base_cost = 0.00;
        }

        $start_date     = ( $start_date && strtotime( $start_date ) ) ? $start_date : null;
        $end_date       = ( $end_date   && strtotime( $end_date ) )   ? $end_date   : null;
        $area_id        = $area_id        ?: null;
        $instructor_id  = $instructor_id  ?: null;
        $finance_cat_id = $finance_cat_id ?: null;
        $duration_weeks = $duration_weeks ?: null;

        if ( $id > 0 ) {
            // UPDATE
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d",
                $id
            ) );
            if ( ! $existing ) {
                wp_send_json_error( [ 'message' => __( 'Curso no encontrado.', 'aura-suite' ) ] );
            }

            $slug = self::generate_slug( $name, $id );

            $result = $wpdb->update(
                $table,
                [
                    'name'           => $name,
                    'slug'           => $slug,
                    'description'    => $description,
                    'area_id'        => $area_id,
                    'instructor_id'  => $instructor_id,
                    'duration_weeks' => $duration_weeks,
                    'max_students'   => $max_students,
                    'base_cost'      => $base_cost,
                    'currency'       => $currency,
                    'status'         => $status,
                    'start_date'     => $start_date,
                    'end_date'       => $end_date,
                    'finance_cat_id' => $finance_cat_id,
                ],
                [ 'id' => $id ]
            );

            if ( $result === false ) {
                wp_send_json_error( [ 'message' => __( 'Error al actualizar el curso. Intente de nuevo.', 'aura-suite' ) ] );
            }

            wp_send_json_success( [
                'message' => __( 'Curso actualizado correctamente.', 'aura-suite' ),
                'id'      => $id,
            ] );

        } else {
            // INSERT
            $slug = self::generate_slug( $name );

            $result = $wpdb->insert(
                $table,
                [
                    'name'           => $name,
                    'slug'           => $slug,
                    'description'    => $description,
                    'area_id'        => $area_id,
                    'instructor_id'  => $instructor_id,
                    'duration_weeks' => $duration_weeks,
                    'max_students'   => $max_students,
                    'base_cost'      => $base_cost,
                    'currency'       => $currency,
                    'status'         => $status,
                    'start_date'     => $start_date,
                    'end_date'       => $end_date,
                    'finance_cat_id' => $finance_cat_id,
                    'created_by'     => get_current_user_id(),
                ]
            );

            if ( ! $result ) {
                wp_send_json_error( [ 'message' => __( 'Error al crear el curso. Intente de nuevo.', 'aura-suite' ) ] );
            }

            wp_send_json_success( [
                'message' => __( 'Curso creado correctamente.', 'aura-suite' ),
                'id'      => $wpdb->insert_id,
            ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ARCHIVAR CURSO (soft-delete)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_delete_course(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_courses_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID de curso inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $enroll_table  = $wpdb->prefix . 'aura_student_enrollments';
        $courses_table = $wpdb->prefix . 'aura_student_courses';

        // Bloquear si hay inscripciones activas o pendientes
        $active_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$enroll_table} WHERE course_id = %d AND status IN ('pending','active')",
            $id
        ) );

        if ( $active_count > 0 ) {
            wp_send_json_error( [
                'message' => sprintf(
                    _n(
                        'No se puede archivar: hay %d inscripción activa.',
                        'No se puede archivar: hay %d inscripciones activas.',
                        $active_count,
                        'aura-suite'
                    ),
                    $active_count
                ),
            ] );
        }

        $result = $wpdb->update( $courses_table, [ 'status' => 'archived' ], [ 'id' => $id ] );

        if ( $result === false ) {
            wp_send_json_error( [ 'message' => __( 'Error al archivar el curso.', 'aura-suite' ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'Curso archivado correctamente.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: OBTENER CURSO POR ID
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_course(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_courses_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT c.*,
                    a.name AS area_name,
                    fc.name AS finance_cat_name,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}aura_student_enrollments e
                     WHERE e.course_id = c.id AND e.status IN ('pending','active')) AS enrolled_count
             FROM {$wpdb->prefix}aura_student_courses c
             LEFT JOIN {$wpdb->prefix}aura_areas a ON a.id = c.area_id
             LEFT JOIN {$wpdb->prefix}aura_finance_categories fc ON fc.id = c.finance_cat_id
             WHERE c.id = %d",
            $id
        ) );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Curso no encontrado.', 'aura-suite' ) ] );
        }

        $row->instructor_name = self::get_instructor_name( (int) $row->instructor_id );

        wp_send_json_success( [ 'course' => self::format_course( $row ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: LISTADO PAGINADO DE CURSOS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_list_courses(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_courses_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $page     = max( 1, absint( $_POST['page'] ?? 1 ) );
        $per_page = 20;
        $offset   = ( $page - 1 ) * $per_page;
        $status   = isset( $_POST['status'] )  ? sanitize_text_field( $_POST['status'] )  : '';
        $area_id  = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] )               : 0;
        $search   = isset( $_POST['search'] )  ? sanitize_text_field( $_POST['search'] )  : '';

        $where  = [ '1=1' ];
        $params = [];

        if ( $status && in_array( $status, [ 'active', 'inactive', 'archived' ], true ) ) {
            $where[]  = 'c.status = %s';
            $params[] = $status;
        }

        if ( $area_id ) {
            $where[]  = 'c.area_id = %d';
            $params[] = $area_id;
        }

        if ( $search ) {
            $where[]  = '(c.name LIKE %s OR c.description LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode( ' AND ', $where );

        // Total rows
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}aura_student_courses c WHERE {$where_sql}";
        $total     = (int) ( $params
            ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
            : $wpdb->get_var( $count_sql )
        );

        // Rows for current page
        $data_sql = "SELECT c.*,
                            a.name AS area_name,
                            fc.name AS finance_cat_name,
                            (SELECT COUNT(*) FROM {$wpdb->prefix}aura_student_enrollments e
                             WHERE e.course_id = c.id AND e.status IN ('pending','active')) AS enrolled_count
                     FROM {$wpdb->prefix}aura_student_courses c
                     LEFT JOIN {$wpdb->prefix}aura_areas a ON a.id = c.area_id
                     LEFT JOIN {$wpdb->prefix}aura_finance_categories fc ON fc.id = c.finance_cat_id
                     WHERE {$where_sql}
                     ORDER BY c.created_at DESC
                     LIMIT %d OFFSET %d";

        $all_params = array_merge( $params, [ $per_page, $offset ] );
        $rows       = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$all_params ) ) ?: [];

        foreach ( $rows as &$r ) {
            $r->instructor_name = self::get_instructor_name( (int) $r->instructor_id );
        }
        unset( $r );

        wp_send_json_success( [
            'courses'      => array_map( [ __CLASS__, 'format_course' ], $rows ),
            'total'        => $total,
            'page'         => $page,
            'total_pages'  => (int) ceil( $total / $per_page ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ÁREAS (para selects del formulario)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_areas(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_courses_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_areas';

        // Tabla puede no existir si el módulo de áreas no está activo
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            wp_send_json_success( [ 'areas' => [] ] );
            return;
        }

        $rows = $wpdb->get_results(
            "SELECT id, name FROM {$table} WHERE status = 'active' ORDER BY name ASC"
        ) ?: [];

        wp_send_json_success( [ 'areas' => $rows ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: INSTRUCTORES (usuarios WP con permisos relevantes)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_instructors(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_courses_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        $admins   = get_users( [ 'role' => 'administrator', 'fields' => [ 'ID', 'display_name' ] ] );
        $editors  = get_users( [ 'role' => 'editor',        'fields' => [ 'ID', 'display_name' ] ] );
        $cap_users = get_users( [ 'capability' => 'aura_students_courses_manage', 'fields' => [ 'ID', 'display_name' ] ] );

        $seen        = [];
        $instructors = [];

        foreach ( array_merge( $admins, $editors, $cap_users ) as $u ) {
            if ( ! isset( $seen[ $u->ID ] ) ) {
                $seen[ $u->ID ] = true;
                $instructors[]  = [ 'id' => (int) $u->ID, 'name' => $u->display_name ];
            }
        }

        usort( $instructors, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );

        wp_send_json_success( [ 'instructors' => $instructors ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: CATEGORÍAS FINANCIERAS TIPO INGRESO
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_finance_cats(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_courses_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            wp_send_json_success( [ 'categories' => [] ] );
            return;
        }

        $rows = $wpdb->get_results(
            "SELECT id, name FROM {$table} WHERE type IN ('income','both') AND is_active = 1 ORDER BY name ASC"
        ) ?: [];

        wp_send_json_success( [ 'categories' => $rows ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Genera slug único para el curso.
     */
    private static function generate_slug( string $name, int $exclude_id = 0 ): string {
        global $wpdb;
        $table  = $wpdb->prefix . 'aura_student_courses';
        $base   = sanitize_title( $name );
        $slug   = $base;
        $suffix = 2;

        while ( true ) {
            if ( $exclude_id > 0 ) {
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE slug = %s AND id != %d LIMIT 1",
                    $slug,
                    $exclude_id
                ) );
            } else {
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
                    $slug
                ) );
            }

            if ( ! $exists ) break;
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Devuelve el nombre visible del instructor.
     */
    private static function get_instructor_name( int $user_id ): string {
        if ( ! $user_id ) return '—';

        $first = get_user_meta( $user_id, 'first_name', true );
        $last  = get_user_meta( $user_id, 'last_name',  true );
        $name  = trim( "$first $last" );

        if ( $name ) return $name;

        $ud = get_userdata( $user_id );
        return $ud ? $ud->display_name : '—';
    }

    /**
     * Normaliza una fila BD para respuesta JSON.
     */
    private static function format_course( object $row ): array {
        return [
            'id'               => (int)   $row->id,
            'name'             =>          $row->name,
            'slug'             =>          $row->slug,
            'description'      =>          $row->description      ?? '',
            'area_id'          => $row->area_id        ? (int) $row->area_id        : null,
            'area_name'        =>          $row->area_name        ?? '—',
            'instructor_id'    => $row->instructor_id  ? (int) $row->instructor_id  : null,
            'instructor_name'  =>          $row->instructor_name  ?? '—',
            'duration_weeks'   => $row->duration_weeks ? (int) $row->duration_weeks : null,
            'max_students'     => (int)   $row->max_students,
            'base_cost'        => (float) $row->base_cost,
            'currency'         =>          $row->currency,
            'status'           =>          $row->status,
            'start_date'       =>          $row->start_date       ?? '',
            'end_date'         =>          $row->end_date         ?? '',
            'finance_cat_id'   => $row->finance_cat_id ? (int) $row->finance_cat_id : null,
            'finance_cat_name' =>          $row->finance_cat_name ?? '—',
            'enrolled_count'   => (int)   ( $row->enrolled_count  ?? 0 ),
            'created_at'       =>          $row->created_at       ?? '',
        ];
    }
}
