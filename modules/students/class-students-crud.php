<?php
/**
 * CRUD de Estudiantes — Fase 3
 *
 * Responsabilidades:
 *  - Registrar hooks AJAX para crear, editar, listar, obtener y eliminar estudiantes
 *  - Servir áreas de tipo 'program' para el multi-select de preferencias
 *  - Cargar los templates de listado y formulario de estudiante
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Students_CRUD {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_students_save',          [ __CLASS__, 'ajax_save_student' ] );
        add_action( 'wp_ajax_aura_students_delete',        [ __CLASS__, 'ajax_delete_student' ] );
        add_action( 'wp_ajax_aura_students_get',           [ __CLASS__, 'ajax_get_student' ] );
        add_action( 'wp_ajax_aura_students_list',          [ __CLASS__, 'ajax_list_students' ] );
        add_action( 'wp_ajax_aura_students_get_programs',  [ __CLASS__, 'ajax_get_programs' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────

    public static function render_list(): void {
        if ( ! current_user_can( 'aura_students_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
        }
        require_once AURA_PLUGIN_DIR . 'templates/students/list.php';
    }

    public static function render_form(): void {
        if ( ! current_user_can( 'aura_students_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
        }
        require_once AURA_PLUGIN_DIR . 'templates/students/student-form.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: GUARDAR ESTUDIANTE (CREAR / EDITAR)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save_student(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        global $wpdb;
        $table = $wpdb->prefix . 'aura_students';

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        // Determinar si es creación o edición y verificar capacidad correspondiente
        if ( $id > 0 ) {
            if ( ! current_user_can( 'aura_students_edit' ) && ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para editar.', 'aura-suite' ) ] );
            }
        } else {
            if ( ! current_user_can( 'aura_students_create' ) && ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para crear.', 'aura-suite' ) ] );
            }
        }

        // ── Sanitize inputs ──────────────────────────────────────
        $first_name     = isset( $_POST['first_name'] )     ? sanitize_text_field( $_POST['first_name'] )      : '';
        $last_name      = isset( $_POST['last_name'] )      ? sanitize_text_field( $_POST['last_name'] )       : '';
        $email          = isset( $_POST['email'] )          ? sanitize_email( $_POST['email'] )                : '';
        $phone          = isset( $_POST['phone'] )          ? sanitize_text_field( $_POST['phone'] )           : '';
        $phone_country  = isset( $_POST['phone_country'] )  ? sanitize_text_field( $_POST['phone_country'] )   : '';
        $id_number      = isset( $_POST['id_number'] )      ? sanitize_text_field( $_POST['id_number'] )       : '';
        $id_type        = isset( $_POST['id_type'] )        ? sanitize_text_field( $_POST['id_type'] )         : 'cedula';
        $birthdate      = isset( $_POST['birthdate'] )      ? sanitize_text_field( $_POST['birthdate'] )       : '';
        $gender         = isset( $_POST['gender'] )         ? sanitize_text_field( $_POST['gender'] )          : '';
        $address        = isset( $_POST['address'] )        ? sanitize_text_field( $_POST['address'] )         : '';
        $city           = isset( $_POST['city'] )           ? sanitize_text_field( $_POST['city'] )            : '';
        $country        = isset( $_POST['country'] )        ? sanitize_text_field( $_POST['country'] )         : '';
        $photo_url      = isset( $_POST['photo_url'] )      ? esc_url_raw( $_POST['photo_url'] )               : '';
        $motivation     = isset( $_POST['motivation'] )     ? sanitize_textarea_field( $_POST['motivation'] )  : '';
        $supported_by   = isset( $_POST['supported_by'] )   ? sanitize_text_field( $_POST['supported_by'] )    : '';
        $talent         = isset( $_POST['talent'] )         ? sanitize_textarea_field( $_POST['talent'] )      : '';
        $experience     = isset( $_POST['experience'] )     ? sanitize_textarea_field( $_POST['experience'] )  : '';
        $extra_info     = isset( $_POST['extra_info'] )     ? sanitize_textarea_field( $_POST['extra_info'] )  : '';
        $profile_type   = isset( $_POST['profile_type'] )   ? sanitize_text_field( $_POST['profile_type'] )    : 'student';
        $status         = isset( $_POST['status'] )         ? sanitize_text_field( $_POST['status'] )          : 'applicant';
        $notes          = isset( $_POST['notes'] )          ? sanitize_textarea_field( $_POST['notes'] )       : '';

        // preferred_areas: array de IDs enteros recibidos como JSON o como array $_POST
        $preferred_areas_raw = isset( $_POST['preferred_areas'] ) ? $_POST['preferred_areas'] : [];
        if ( is_string( $preferred_areas_raw ) ) {
            $preferred_areas_raw = json_decode( wp_unslash( $preferred_areas_raw ), true ) ?: [];
        }
        $preferred_areas_ids = array_map( 'absint', (array) $preferred_areas_raw );
        $preferred_areas_ids = array_values( array_filter( $preferred_areas_ids ) );
        $preferred_areas     = ! empty( $preferred_areas_ids ) ? wp_json_encode( $preferred_areas_ids ) : null;

        // ── Validaciones ─────────────────────────────────────────
        if ( empty( $first_name ) ) {
            wp_send_json_error( [ 'message' => __( 'El nombre es obligatorio.', 'aura-suite' ) ] );
        }

        if ( empty( $last_name ) ) {
            wp_send_json_error( [ 'message' => __( 'El apellido es obligatorio.', 'aura-suite' ) ] );
        }

        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'El correo electrónico no es válido.', 'aura-suite' ) ] );
        }

        $valid_profiles = [ 'student', 'volunteer', 'teacher', 'participant', 'intern' ];
        if ( ! in_array( $profile_type, $valid_profiles, true ) ) {
            $profile_type = 'student';
        }

        $valid_statuses = [ 'applicant', 'approved', 'active', 'graduated', 'withdrawn', 'rejected' ];
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            $status = 'applicant';
        }

        $valid_id_types = [ 'cedula', 'passport', 'ruc', 'dni', 'other' ];
        if ( ! in_array( $id_type, $valid_id_types, true ) ) {
            $id_type = 'cedula';
        }

        $birthdate = ( $birthdate && strtotime( $birthdate ) ) ? $birthdate : null;

        // Solo quien puede editar puede cambiar el status
        if ( $id > 0 && ! current_user_can( 'aura_students_edit' ) && ! current_user_can( 'manage_options' ) ) {
            // Re-usar el status existente
            $current_status = $wpdb->get_var( $wpdb->prepare(
                "SELECT status FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id
            ) );
            $status = $current_status ?: 'applicant';
        }

        if ( $id > 0 ) {
            // ── UPDATE ───────────────────────────────────────────
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d AND deleted_at IS NULL",
                $id
            ) );
            if ( ! $existing ) {
                wp_send_json_error( [ 'message' => __( 'Estudiante no encontrado.', 'aura-suite' ) ] );
            }

            // Unicidad de email (excluir al propio registro y eliminados)
            $email_dupe = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE email = %s AND id != %d AND deleted_at IS NULL LIMIT 1",
                $email,
                $id
            ) );
            if ( $email_dupe ) {
                wp_send_json_error( [ 'message' => __( 'Ya existe otro estudiante con ese correo electrónico.', 'aura-suite' ) ] );
            }

            $result = $wpdb->update(
                $table,
                [
                    'first_name'      => $first_name,
                    'last_name'       => $last_name,
                    'email'           => $email,
                    'phone'           => $phone,
                    'phone_country'   => $phone_country,
                    'id_number'       => $id_number,
                    'id_type'         => $id_type,
                    'birthdate'       => $birthdate,
                    'gender'          => $gender,
                    'address'         => $address,
                    'city'            => $city,
                    'country'         => $country,
                    'photo_url'       => $photo_url,
                    'preferred_areas' => $preferred_areas,
                    'motivation'      => $motivation,
                    'supported_by'    => $supported_by,
                    'talent'          => $talent,
                    'experience'      => $experience,
                    'extra_info'      => $extra_info,
                    'profile_type'    => $profile_type,
                    'status'          => $status,
                    'notes'           => $notes,
                    'updated_at'      => current_time( 'mysql' ),
                ],
                [ 'id' => $id ]
            );

            if ( $result === false ) {
                wp_send_json_error( [ 'message' => __( 'Error al actualizar el estudiante.', 'aura-suite' ) ] );
            }

            wp_send_json_success( [
                'message' => __( 'Estudiante actualizado correctamente.', 'aura-suite' ),
                'id'      => $id,
            ] );

        } else {
            // ── INSERT ───────────────────────────────────────────

            // Unicidad de email entre registros no eliminados
            $email_dupe = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE email = %s AND deleted_at IS NULL LIMIT 1",
                $email
            ) );
            if ( $email_dupe ) {
                wp_send_json_error( [ 'message' => __( 'Ya existe un estudiante registrado con ese correo electrónico.', 'aura-suite' ) ] );
            }

            $result = $wpdb->insert(
                $table,
                [
                    'first_name'      => $first_name,
                    'last_name'       => $last_name,
                    'email'           => $email,
                    'phone'           => $phone,
                    'phone_country'   => $phone_country,
                    'id_number'       => $id_number,
                    'id_type'         => $id_type,
                    'birthdate'       => $birthdate,
                    'gender'          => $gender,
                    'address'         => $address,
                    'city'            => $city,
                    'country'         => $country,
                    'photo_url'       => $photo_url,
                    'preferred_areas' => $preferred_areas,
                    'motivation'      => $motivation,
                    'supported_by'    => $supported_by,
                    'talent'          => $talent,
                    'experience'      => $experience,
                    'extra_info'      => $extra_info,
                    'profile_type'    => $profile_type,
                    'status'          => 'applicant', // Siempre inician como postulante
                    'notes'           => $notes,
                    'created_by'      => get_current_user_id(),
                ]
            );

            if ( ! $result ) {
                wp_send_json_error( [ 'message' => __( 'Error al registrar el estudiante.', 'aura-suite' ) ] );
            }

            wp_send_json_success( [
                'message' => __( 'Estudiante registrado correctamente.', 'aura-suite' ),
                'id'      => $wpdb->insert_id,
            ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ELIMINAR ESTUDIANTE (soft-delete)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_delete_student(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_delete' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID de estudiante inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_students';

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id
        ) );

        if ( ! $existing ) {
            wp_send_json_error( [ 'message' => __( 'Estudiante no encontrado.', 'aura-suite' ) ] );
        }

        $result = $wpdb->update(
            $table,
            [ 'deleted_at' => current_time( 'mysql' ) ],
            [ 'id'         => $id ]
        );

        if ( $result === false ) {
            wp_send_json_error( [ 'message' => __( 'Error al eliminar el estudiante.', 'aura-suite' ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'Estudiante eliminado correctamente.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: OBTENER ESTUDIANTE POR ID
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_student(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_view_all' ) &&
             ! current_user_can( 'aura_students_edit' ) &&
             ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.*,
                    u.display_name AS approver_name
             FROM {$wpdb->prefix}aura_students s
             LEFT JOIN {$wpdb->users} u ON u.ID = s.approved_by
             WHERE s.id = %d AND s.deleted_at IS NULL",
            $id
        ) );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Estudiante no encontrado.', 'aura-suite' ) ] );
        }

        // Inscripciones activas del estudiante
        $enrollments_table = $wpdb->prefix . 'aura_student_enrollments';
        $courses_table     = $wpdb->prefix . 'aura_student_courses';

        $enrollments = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.id, e.status, e.scholarship_pct, e.net_cost, c.currency,
                    e.enrollment_date, c.name AS course_name
             FROM {$enrollments_table} e
             JOIN {$courses_table} c ON c.id = e.course_id
             WHERE e.student_id = %d
             ORDER BY e.enrollment_date DESC",
            $id
        ) ) ?: [];

        // Pagos del estudiante
        $payments_table = $wpdb->prefix . 'aura_student_payments';

        $payments = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.id, p.amount, p.payment_method, p.payment_date,
                    p.reference_number, p.notes, c.currency,
                    c.name AS course_name
             FROM {$payments_table} p
             LEFT JOIN {$courses_table} c ON c.id = p.course_id
             WHERE p.student_id = %d
             ORDER BY p.payment_date DESC
             LIMIT 50",
            $id
        ) ) ?: [];

        wp_send_json_success( [
            'student'     => self::format_student( $row ),
            'enrollments' => array_map( function( $e ) {
                return [
                    'id'             => (int)   $e->id,
                    'course_name'    =>          $e->course_name,
                    'status'         =>          $e->status,
                    'scholarship_pct'=> (float)  $e->scholarship_pct,
                    'net_cost'       => (float)  $e->net_cost,
                    'currency'       =>          $e->currency ?? 'USD',
                    'enrolled_at'    =>          $e->enrollment_date,
                ];
            }, $enrollments ),
            'payments'    => array_map( function( $p ) {
                return [
                    'id'             => (int)   $p->id,
                    'course_name'    =>          $p->course_name ?? '—',
                    'amount'         => (float)  $p->amount,
                    'currency'       =>          $p->currency ?? 'USD',
                    'payment_method' =>          $p->payment_method,
                    'payment_date'   =>          $p->payment_date,
                    'reference'      =>          $p->reference_number ?? '',
                    'notes'          =>          $p->notes ?? '',
                ];
            }, $payments ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: LISTADO PAGINADO DE ESTUDIANTES
    // ─────────────────────────────────────────────────────────────

    public static function ajax_list_students(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $page         = max( 1, absint( $_POST['page'] ?? 1 ) );
        $per_page     = 20;
        $offset       = ( $page - 1 ) * $per_page;
        $status       = isset( $_POST['status'] )       ? sanitize_text_field( $_POST['status'] )       : '';
        $profile_type = isset( $_POST['profile_type'] ) ? sanitize_text_field( $_POST['profile_type'] ) : '';
        $area_id      = isset( $_POST['area_id'] )      ? absint( $_POST['area_id'] )                    : 0;
        $search       = isset( $_POST['search'] )       ? sanitize_text_field( $_POST['search'] )       : '';

        $where  = [ 's.deleted_at IS NULL' ];
        $params = [];

        $valid_statuses = [ 'applicant', 'approved', 'active', 'graduated', 'withdrawn', 'rejected' ];
        if ( $status && in_array( $status, $valid_statuses, true ) ) {
            $where[]  = 's.status = %s';
            $params[] = $status;
        }

        $valid_profiles = [ 'student', 'volunteer', 'teacher', 'participant', 'intern' ];
        if ( $profile_type && in_array( $profile_type, $valid_profiles, true ) ) {
            $where[]  = 's.profile_type = %s';
            $params[] = $profile_type;
        }

        if ( $area_id ) {
            $where[]  = 'JSON_CONTAINS(COALESCE(s.preferred_areas, \'[]\'), %s)';
            $params[] = (string) $area_id;
        }

        if ( $search ) {
            $like      = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]   = '(s.first_name LIKE %s OR s.last_name LIKE %s OR s.email LIKE %s)';
            $params[]  = $like;
            $params[]  = $like;
            $params[]  = $like;
        }

        $where_sql = implode( ' AND ', $where );

        // Contar total
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}aura_students s WHERE {$where_sql}";
        $total     = (int) ( $params
            ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
            : $wpdb->get_var( $count_sql )
        );

        // Consulta paginada con conteo de inscripciones activas y saldo pendiente
        $enrollments_table = $wpdb->prefix . 'aura_student_enrollments';
        $payments_table    = $wpdb->prefix . 'aura_student_payments';

        $data_sql = "SELECT s.*,
                            (SELECT COUNT(*) FROM {$enrollments_table} e
                             WHERE e.student_id = s.id AND e.status IN ('pending','active')) AS active_enrollments,
                            (SELECT COALESCE(SUM(e2.balance_due), 0)
                             FROM {$enrollments_table} e2
                             WHERE e2.student_id = s.id AND e2.balance_due > 0) AS pending_balance
                     FROM {$wpdb->prefix}aura_students s
                     WHERE {$where_sql}
                     ORDER BY s.created_at DESC
                     LIMIT %d OFFSET %d";

        $all_params = array_merge( $params, [ $per_page, $offset ] );
        $rows       = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$all_params ) ) ?: [];

        wp_send_json_success( [
            'students'    => array_map( [ __CLASS__, 'format_student_list_row' ], $rows ),
            'total'       => $total,
            'page'        => $page,
            'total_pages' => (int) ceil( $total / $per_page ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ÁREAS TIPO PROGRAMA (para preferred_areas)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_programs(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_create' ) &&
             ! current_user_can( 'aura_students_edit' ) &&
             ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_areas';

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            wp_send_json_success( [ 'programs' => [] ] );
            return;
        }

        $rows = $wpdb->get_results(
            "SELECT id, name FROM {$table} WHERE type = 'program' AND status = 'active' ORDER BY name ASC"
        ) ?: [];

        wp_send_json_success( [ 'programs' => $rows ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Normaliza una fila BD (lista) para respuesta JSON.
     */
    private static function format_student_list_row( object $row ): array {
        return [
            'id'                  => (int)   $row->id,
            'first_name'          =>          $row->first_name,
            'last_name'           =>          $row->last_name,
            'full_name'           => trim( $row->first_name . ' ' . $row->last_name ),
            'email'               =>          $row->email,
            'phone'               =>          $row->phone          ?? '',
            'profile_type'        =>          $row->profile_type,
            'status'              =>          $row->status,
            'photo_url'           =>          $row->photo_url      ?? '',
            'city'                =>          $row->city           ?? '',
            'country'             =>          $row->country        ?? '',
            'active_enrollments'  => (int)   ( $row->active_enrollments ?? 0 ),
            'pending_balance'     => (float) ( $row->pending_balance    ?? 0 ),
            'created_at'          =>          $row->created_at     ?? '',
        ];
    }

    /**
     * Normaliza una fila BD (detalle completo) para respuesta JSON.
     */
    private static function format_student( object $row ): array {
        $preferred_areas_ids = [];
        if ( ! empty( $row->preferred_areas ) ) {
            $decoded = json_decode( $row->preferred_areas, true );
            if ( is_array( $decoded ) ) {
                $preferred_areas_ids = array_map( 'intval', $decoded );
            }
        }

        return [
            'id'               => (int)   $row->id,
            'wp_user_id'       => $row->wp_user_id ? (int) $row->wp_user_id : null,
            'profile_type'     =>          $row->profile_type,
            'first_name'       =>          $row->first_name,
            'last_name'        =>          $row->last_name,
            'full_name'        => trim( $row->first_name . ' ' . $row->last_name ),
            'email'            =>          $row->email,
            'phone'            =>          $row->phone           ?? '',
            'phone_country'    =>          $row->phone_country   ?? '',
            'id_number'        =>          $row->id_number       ?? '',
            'id_type'          =>          $row->id_type         ?? 'cedula',
            'birthdate'        =>          $row->birthdate       ?? '',
            'gender'           =>          $row->gender          ?? '',
            'address'          =>          $row->address         ?? '',
            'city'             =>          $row->city            ?? '',
            'country'          =>          $row->country         ?? '',
            'photo_url'        =>          $row->photo_url       ?? '',
            'preferred_areas'  =>          $preferred_areas_ids,
            'motivation'       =>          $row->motivation      ?? '',
            'supported_by'     =>          $row->supported_by    ?? '',
            'talent'           =>          $row->talent          ?? '',
            'experience'       =>          $row->experience      ?? '',
            'extra_info'       =>          $row->extra_info      ?? '',
            'status'           =>          $row->status,
            'rejection_reason' =>          $row->rejection_reason ?? '',
            'approved_by'      => $row->approved_by ? (int) $row->approved_by : null,
            'approver_name'    =>          $row->approver_name   ?? '—',
            'approved_at'      =>          $row->approved_at     ?? '',
            'graduated_at'     =>          $row->graduated_at    ?? '',
            'notes'            =>          $row->notes           ?? '',
            'created_at'       =>          $row->created_at      ?? '',
            'updated_at'       =>          $row->updated_at      ?? '',
        ];
    }
}
