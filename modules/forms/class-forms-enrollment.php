<?php
/**
 * Enrollment Bridge del Módulo de Formularios
 *
 * Escucha el hook 'aura_form_submission_saved' y, cuando el formulario es
 * de tipo 'enrollment', crea automáticamente un perfil de postulante en
 * aura_students (si no existe) y un enrollment pendiente en aura_student_enrollments.
 *
 * AJAX actions registradas:
 *  - aura_forms_approve_enrollment — Aprobar postulante
 *  - aura_forms_reject_enrollment  — Rechazar con motivo
 *  - aura_forms_mark_withdrawn     — Marcar como retirado
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Forms_Enrollment {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'aura_form_submission_saved', [ __CLASS__, 'maybe_create_enrollment' ], 10, 2 );
        add_action( 'wp_ajax_aura_forms_approve_enrollment',  [ __CLASS__, 'ajax_approve_enrollment' ] );
        add_action( 'wp_ajax_aura_forms_reject_enrollment',   [ __CLASS__, 'ajax_reject_enrollment' ] );
        add_action( 'wp_ajax_aura_forms_mark_withdrawn',      [ __CLASS__, 'ajax_mark_withdrawn' ] );
        add_action( 'wp_ajax_aura_forms_get_field_labels',    [ __CLASS__, 'ajax_get_field_labels' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: OBTENER ETIQUETAS DE CAMPOS PARA EL MODAL DE DETALLE
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve un mapa { field_uid => label } para un formulario.
     * Usado por el modal de detalle del panel de postulantes.
     */
    public static function ajax_get_field_labels(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_enrollment_review' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        if ( ! $form_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de formulario inválido.', 'aura-suite' ) ] );
        }

        $fields = $wpdb->get_results( $wpdb->prepare(
            "SELECT field_uid, label FROM {$wpdb->prefix}aura_form_fields
              WHERE form_id = %d
              ORDER BY sort_order ASC",
            $form_id
        ) );

        $labels = [];
        foreach ( $fields as $f ) {
            $labels[ $f->field_uid ] = $f->label;
        }

        wp_send_json_success( [ 'labels' => $labels ] );
    }

    // ─────────────────────────────────────────────────────────────
    // BRIDGE: SUBMISSION → ENROLLMENT
    // ─────────────────────────────────────────────────────────────

    /**
     * Escucha cada nuevo submission. Si el formulario es de tipo 'enrollment',
     * ejecuta find_or_create_student() y crea un enrollment pendiente.
     */
    public static function maybe_create_enrollment( int $submission_id, int $form_id ): void {
        global $wpdb;

        // 1. Cargar formulario y verificar tipo
        $form = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, type, course_id, area_id FROM {$wpdb->prefix}aura_forms WHERE id = %d AND deleted_at IS NULL",
            $form_id
        ) );

        if ( ! $form || $form->type !== 'enrollment' ) {
            return;
        }

        // 2. Cargar submission y decodificar data_json
        $submission = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, data_json, enrollment_id FROM {$wpdb->prefix}aura_form_submissions WHERE id = %d",
            $submission_id
        ) );

        if ( ! $submission ) {
            return;
        }

        // Ya fue procesado (puede dispararse dos veces en edge‑cases)
        if ( ! empty( $submission->enrollment_id ) ) {
            return;
        }

        $data = json_decode( $submission->data_json, true );
        if ( ! is_array( $data ) ) {
            return;
        }

        // 3. Construir mapa uid → mapping_key a partir de aura_form_fields
        $fields = $wpdb->get_results( $wpdb->prepare(
            "SELECT field_uid, mapping_key FROM {$wpdb->prefix}aura_form_fields
              WHERE form_id = %d AND mapping_key IS NOT NULL AND mapping_key != ''",
            $form_id
        ) );

        $uid_to_key = [];
        foreach ( $fields as $f ) {
            $uid_to_key[ $f->field_uid ] = $f->mapping_key;
        }

        // 4. Extraer campos mapeados desde data_json
        $mapped = self::extract_mapped_fields( $data, $uid_to_key );

        // Email obligatorio
        $email = sanitize_email( $mapped['email'] ?? '' );
        if ( ! is_email( $email ) ) {
            return;
        }

        // course_id: primero desde campo mapeado, luego desde el formulario mismo
        $course_id = absint( $mapped['course_id'] ?? 0 );
        if ( ! $course_id ) {
            $course_id = absint( $form->course_id ?? 0 );
        }

        // 5. Encontrar o crear el estudiante
        $student_id = self::find_or_create_student( $mapped, $email );
        if ( ! $student_id ) {
            return;
        }

        // 6. Crear enrollment pendiente (respeta UNIQUE KEY uk_student_course)
        $enrollment_id = self::create_pending_enrollment( $student_id, $course_id, $submission_id, $mapped );

        if ( ! $enrollment_id ) {
            // Recuperar enrollment existente para actualizar el submission
            if ( $course_id ) {
                $enrollment_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}aura_student_enrollments
                      WHERE student_id = %d AND course_id = %d
                      ORDER BY id DESC LIMIT 1",
                    $student_id,
                    $course_id
                ) );
            }
        }

        // 7. Vincular submission ↔ enrollment
        if ( $enrollment_id ) {
            $wpdb->update(
                $wpdb->prefix . 'aura_form_submissions',
                [ 'enrollment_id' => $enrollment_id ],
                [ 'id' => $submission_id ],
                [ '%d' ],
                [ '%d' ]
            );
        }

        // 8. Hook para extensiones
        do_action( 'aura_form_enrollment_submitted', $submission_id, $form_id, $enrollment_id );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Extrae los campos mapeados de data_json usando el mapa uid→mapping_key.
     * data_json puede tener claves = uid (nuevo) o mapping_key directamente (compatibilidad).
     *
     * @param array $data       Array de data_json del submission.
     * @param array $uid_to_key Mapa uid → mapping_key.
     * @return array            Array indexado por mapping_key.
     */
    private static function extract_mapped_fields( array $data, array $uid_to_key ): array {
        $result = [];

        foreach ( $data as $key => $value ) {
            // Fecha de nacimiento: la clave _iso_date contiene la fecha real
            if ( str_ends_with( $key, '_iso_date' ) ) {
                $base_uid = substr( $key, 0, -9 ); // quitar '_iso_date'
                if ( isset( $uid_to_key[ $base_uid ] ) && $uid_to_key[ $base_uid ] === 'birthdate' ) {
                    $result['birthdate'] = $value;
                }
                continue;
            }

            if ( isset( $uid_to_key[ $key ] ) ) {
                $mapping = $uid_to_key[ $key ];
                // Para birthdate, el uid directo guarda la edad; usamos _iso_date (procesado arriba)
                if ( $mapping === 'birthdate' ) {
                    continue;
                }
                $result[ $mapping ] = $value;
            } else {
                // Compatibilidad: clave ya es mapping_key
                $result[ $key ] = $value;
            }
        }

        return $result;
    }

    /**
     * Busca un estudiante por email o lo crea con estado 'applicant'.
     *
     * @param array  $mapped Campos mapeados.
     * @param string $email  Email del postulante.
     * @return int|null      ID en aura_students o null en caso de error.
     */
    private static function find_or_create_student( array $mapped, string $email ): ?int {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_students';

        // Buscar por email (ignorar eliminados)
        $existing_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$table}` WHERE email = %s AND deleted_at IS NULL ORDER BY id ASC LIMIT 1",
            $email
        ) );

        if ( $existing_id ) {
            return (int) $existing_id;
        }

        // Extraer y sanear campos de alta
        $first_name = sanitize_text_field( $mapped['first_name'] ?? '' );
        $last_name  = sanitize_text_field( $mapped['last_name']  ?? '' );
        $phone      = sanitize_text_field( $mapped['phone']      ?? '' );
        $id_number  = sanitize_text_field( $mapped['id_number']  ?? '' );
        $id_type    = sanitize_text_field( $mapped['id_type']    ?? '' );
        $birthdate  = sanitize_text_field( $mapped['birthdate']  ?? '' );
        $gender     = sanitize_text_field( $mapped['gender']     ?? '' );
        $address    = sanitize_text_field( $mapped['address']    ?? '' );
        $city       = sanitize_text_field( $mapped['city']       ?? '' );
        $country    = sanitize_text_field( $mapped['country']    ?? '' );
        $motivation = sanitize_textarea_field( $mapped['motivation'] ?? '' );

        // Normalizar id_type al ENUM de la tabla (cedula, pasaporte, dni, otro)
        $id_type_map = [
            'cedula'    => 'cedula',
            'pasaporte' => 'pasaporte',
            'passport'  => 'pasaporte',
            'dni'       => 'dni',
            'otro'      => 'otro',
            'other'     => 'otro',
        ];
        $id_type = $id_type_map[ strtolower( $id_type ) ] ?? null;

        // Normalizar gender al ENUM de la tabla (M, F, otro, prefiero_no_decir)
        $gender_map = [
            'm'                 => 'M',
            'masculino'         => 'M',
            'hombre'            => 'M',
            'male'              => 'M',
            'f'                 => 'F',
            'femenino'          => 'F',
            'mujer'             => 'F',
            'female'            => 'F',
            'otro'              => 'otro',
            'other'             => 'otro',
            'o'                 => 'otro',
            'prefiero_no_decir' => 'prefiero_no_decir',
            'p'                 => 'prefiero_no_decir',
        ];
        $gender = $gender_map[ strtolower( $gender ) ] ?? null;

        // Validar birthdate
        if ( $birthdate && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $birthdate ) ) {
            $birthdate = '';
        }

        // Construir arrays dinámicamente para omitir columnas ENUM con valor null
        // ($wpdb->prepare convierte null en '' que viola ENUM constraints)
        $insert_data = [
            'profile_type' => 'student',
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'email'        => $email,
            'phone'        => $phone,
            'id_number'    => $id_number,
            'address'      => $address,
            'city'         => $city,
            'country'      => $country,
            'motivation'   => $motivation,
            'status'       => 'applicant',
            'created_by'   => 0,
            'created_at'   => current_time( 'mysql' ),
            'updated_at'   => current_time( 'mysql' ),
        ];
        $insert_format = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ];

        // Solo incluir ENUM/date si tienen valor válido (evita '' en MySQL strict)
        if ( $id_type !== null ) {
            $insert_data['id_type'] = $id_type;
            $insert_format[] = '%s';
        }
        if ( $gender !== null ) {
            $insert_data['gender'] = $gender;
            $insert_format[] = '%s';
        }
        if ( $birthdate ) {
            $insert_data['birthdate'] = $birthdate;
            $insert_format[] = '%s';
        }

        $inserted = $wpdb->insert( $table, $insert_data, $insert_format );

        if ( ! $inserted ) {
            return null;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Crea un enrollment en estado 'pending' con enrolled_by = 0 (auto).
     * Respeta UNIQUE KEY uk_student_course: si ya existe no inserta un duplicado.
     *
     * @param int   $student_id    ID del estudiante.
     * @param int   $course_id     ID del curso (puede ser 0 si el formulario no lo requiere).
     * @param int   $submission_id ID de la submission de origen.
     * @param array $mapped        Campos mapeados.
     * @return int|null            ID del nuevo enrollment o null si hay error o ya existía.
     */
    private static function create_pending_enrollment(
        int $student_id,
        int $course_id,
        int $submission_id,
        array $mapped
    ): ?int {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_student_enrollments';

        // Si no hay course_id no hay enrollment técnico (el formulario solo recopila datos)
        if ( ! $course_id ) {
            return null;
        }

        // Verificar duplicado activo
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$table}`
              WHERE student_id = %d AND course_id = %d AND status NOT IN ('withdrawn','suspended')
              LIMIT 1",
            $student_id,
            $course_id
        ) );

        if ( $exists ) {
            return null; // Ya existe, el caller recuperará el ID existente
        }

        $notes = sprintf(
            /* translators: submission_id */
            __( 'Inscripción automática desde formulario (submission #%d)', 'aura-suite' ),
            $submission_id
        );

        if ( ! empty( $mapped['notes'] ) ) {
            $notes .= "\n" . sanitize_textarea_field( $mapped['notes'] );
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'student_id'      => $student_id,
                'course_id'       => $course_id,
                'enrollment_date' => current_time( 'Y-m-d' ),
                'status'          => 'pending',
                'base_cost'       => 0.00,
                'net_cost'        => 0.00,
                'payment_scheme'  => 'full',
                'installment_count' => 1,
                'installment_amount' => 0.00,
                'total_paid'      => 0.00,
                'balance_due'     => 0.00,
                'payment_status'  => 'unpaid',
                'enrolled_by'     => 0,
                'notes'           => $notes,
                'created_at'      => current_time( 'mysql' ),
                'updated_at'      => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%f', '%f', '%s', '%d', '%f', '%f', '%f', '%s', '%d', '%s', '%s', '%s' ]
        );

        if ( ! $inserted ) {
            return null;
        }

        return (int) $wpdb->insert_id;
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: APROBAR POSTULANTE
    // ─────────────────────────────────────────────────────────────

    public static function ajax_approve_enrollment(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_enrollment_review' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
        if ( ! $submission_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de submission inválido.', 'aura-suite' ) ] );
        }

        // Cargar submission
        $submission = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, enrollment_id FROM {$wpdb->prefix}aura_form_submissions WHERE id = %d",
            $submission_id
        ) );

        if ( ! $submission || ! $submission->enrollment_id ) {
            wp_send_json_error( [ 'message' => __( 'No se encontró el enrollment vinculado.', 'aura-suite' ) ] );
        }

        $enrollment_id = (int) $submission->enrollment_id;

        // Cargar enrollment → obtener student_id
        $enrollment = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, student_id, status FROM {$wpdb->prefix}aura_student_enrollments WHERE id = %d",
            $enrollment_id
        ) );

        if ( ! $enrollment ) {
            wp_send_json_error( [ 'message' => __( 'Enrollment no encontrado.', 'aura-suite' ) ] );
        }

        $student_id = (int) $enrollment->student_id;

        // Cargar estudiante
        $student = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aura_students WHERE id = %d",
            $student_id
        ) );

        if ( ! $student ) {
            wp_send_json_error( [ 'message' => __( 'Estudiante no encontrado.', 'aura-suite' ) ] );
        }

        // ── Crear / recuperar usuario WP ──────────────────────────
        $wp_user_id = $student->wp_user_id ? absint( $student->wp_user_id ) : 0;
        $password   = null;

        if ( ! $wp_user_id ) {
            if ( email_exists( $student->email ) ) {
                $existing_user = get_user_by( 'email', $student->email );
                $wp_user_id    = $existing_user ? $existing_user->ID : 0;
            } else {
                $password   = wp_generate_password( 12, true, false );
                $wp_user_id = wp_insert_user( [
                    'user_login' => $student->email,
                    'user_email' => $student->email,
                    'user_pass'  => $password,
                    'role'       => 'subscriber',
                    'first_name' => $student->first_name,
                    'last_name'  => $student->last_name,
                ] );

                if ( is_wp_error( $wp_user_id ) ) {
                    wp_send_json_error( [ 'message' => $wp_user_id->get_error_message() ] );
                }
            }

            // Capacidades del portal estudiante
            $user = new WP_User( $wp_user_id );
            $user->add_cap( 'aura_students_view_own' );
            $user->add_cap( 'aura_students_payments_view_own' );
        }

        // ── Actualizar estudiante ─────────────────────────────────
        if ( in_array( $student->status, [ 'applicant', 'approved' ], true ) ) {
            $wpdb->update(
                $wpdb->prefix . 'aura_students',
                [
                    'status'      => 'approved',
                    'wp_user_id'  => $wp_user_id,
                    'approved_by' => get_current_user_id(),
                    'approved_at' => current_time( 'mysql' ),
                    'updated_at'  => current_time( 'mysql' ),
                ],
                [ 'id' => $student_id ],
                [ '%s', '%d', '%d', '%s', '%s' ],
                [ '%d' ]
            );
        }

        // ── Activar enrollment ────────────────────────────────────
        $wpdb->update(
            $wpdb->prefix . 'aura_student_enrollments',
            [
                'status'     => 'active',
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $enrollment_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        // ── Notificación ──────────────────────────────────────────
        if ( $password && class_exists( 'Aura_Students_Notifications' ) ) {
            Aura_Students_Notifications::send_approval_email( $student_id, $password );
        }

        do_action( 'aura_student_enrollment_approved', $student_id, $enrollment_id );

        wp_send_json_success( [
            'message'       => __( 'Postulante aprobado. Se activó la inscripción al curso.', 'aura-suite' ),
            'enrollment_id' => $enrollment_id,
            'wp_user_id'    => $wp_user_id,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: RECHAZAR POSTULANTE
    // ─────────────────────────────────────────────────────────────

    public static function ajax_reject_enrollment(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_enrollment_review' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $submission_id    = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] )              : 0;
        $rejection_reason = isset( $_POST['rejection_reason'] ) ? sanitize_textarea_field( $_POST['rejection_reason'] ) : '';

        if ( ! $submission_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de submission inválido.', 'aura-suite' ) ] );
        }

        $submission = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, enrollment_id FROM {$wpdb->prefix}aura_form_submissions WHERE id = %d",
            $submission_id
        ) );

        if ( ! $submission || ! $submission->enrollment_id ) {
            wp_send_json_error( [ 'message' => __( 'No se encontró el enrollment vinculado.', 'aura-suite' ) ] );
        }

        $enrollment_id = (int) $submission->enrollment_id;

        $enrollment = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, student_id FROM {$wpdb->prefix}aura_student_enrollments WHERE id = %d",
            $enrollment_id
        ) );

        if ( ! $enrollment ) {
            wp_send_json_error( [ 'message' => __( 'Enrollment no encontrado.', 'aura-suite' ) ] );
        }

        $student_id = (int) $enrollment->student_id;

        // Marcar enrollment como retirado (no existe estado "rechazado" en enrollments)
        $wpdb->update(
            $wpdb->prefix . 'aura_student_enrollments',
            [ 'status' => 'withdrawn', 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $enrollment_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        // Marcar estudiante como rechazado
        $wpdb->update(
            $wpdb->prefix . 'aura_students',
            [
                'status'           => 'rejected',
                'rejection_reason' => $rejection_reason,
                'updated_at'       => current_time( 'mysql' ),
            ],
            [ 'id' => $student_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        // Notificación
        if ( class_exists( 'Aura_Students_Notifications' ) ) {
            Aura_Students_Notifications::send_rejection_email( $student_id, $rejection_reason );
        }

        do_action( 'aura_student_enrollment_rejected', $student_id, $enrollment_id, $rejection_reason );

        wp_send_json_success( [ 'message' => __( 'Postulante rechazado. Se notificó al interesado.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: MARCAR COMO RETIRADO
    // ─────────────────────────────────────────────────────────────

    public static function ajax_mark_withdrawn(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_enrollment_review' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
        if ( ! $submission_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de submission inválido.', 'aura-suite' ) ] );
        }

        $submission = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, enrollment_id FROM {$wpdb->prefix}aura_form_submissions WHERE id = %d",
            $submission_id
        ) );

        if ( ! $submission || ! $submission->enrollment_id ) {
            wp_send_json_error( [ 'message' => __( 'No se encontró el enrollment vinculado.', 'aura-suite' ) ] );
        }

        $enrollment_id = (int) $submission->enrollment_id;

        $enrollment = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, student_id FROM {$wpdb->prefix}aura_student_enrollments WHERE id = %d",
            $enrollment_id
        ) );

        if ( ! $enrollment ) {
            wp_send_json_error( [ 'message' => __( 'Enrollment no encontrado.', 'aura-suite' ) ] );
        }

        $student_id = (int) $enrollment->student_id;

        // Marcar enrollment como retirado
        $wpdb->update(
            $wpdb->prefix . 'aura_student_enrollments',
            [ 'status' => 'withdrawn', 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $enrollment_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        // Marcar estudiante como retirado (solo si aún es applicant/approved)
        $student = $wpdb->get_row( $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}aura_students WHERE id = %d",
            $student_id
        ) );

        if ( $student && in_array( $student->status, [ 'applicant', 'approved' ], true ) ) {
            $wpdb->update(
                $wpdb->prefix . 'aura_students',
                [ 'status' => 'withdrawn', 'updated_at' => current_time( 'mysql' ) ],
                [ 'id' => $student_id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );
        }

        do_action( 'aura_student_enrollment_withdrawn', $student_id, $enrollment_id );

        wp_send_json_success( [ 'message' => __( 'Postulante marcado como retirado.', 'aura-suite' ) ] );
    }
}
