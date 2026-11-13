<?php
/**
 * Emisión de Certificados
 *
 * Maneja la emisión individual, emisión masiva y descarga de PDFs.
 *
 * @package AuraBusinessSuite
 * @subpackage Certificates
 * @since 1.7.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Certificates_Issuer {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        // Emisión individual
        add_action( 'wp_ajax_aura_cert_issue',          [ __CLASS__, 'ajax_issue' ] );
        // Búsqueda rápida de estudiantes e inscripciones (pantalla emitir)
        add_action( 'wp_ajax_aura_cert_search_students',[ __CLASS__, 'ajax_search_students' ] );
        // Reenviar email de certificado
        add_action( 'wp_ajax_aura_cert_resend_email',   [ __CLASS__, 'ajax_resend_email' ] );
        // Revocar
        add_action( 'wp_ajax_aura_cert_revoke',         [ __CLASS__, 'ajax_revoke' ] );
        // Descarga segura del PDF (autenticado)
        add_action( 'wp_ajax_aura_cert_download',       [ __CLASS__, 'ajax_download' ] );
        // Descarga pública (con token en URL)
        add_action( 'wp_ajax_nopriv_aura_cert_download',[ __CLASS__, 'ajax_download' ] );
        // Emisión masiva
        add_action( 'wp_ajax_aura_cert_queue_bulk',     [ __CLASS__, 'ajax_queue_bulk' ] );
        add_action( 'wp_ajax_aura_cert_bulk_status',    [ __CLASS__, 'ajax_bulk_status' ] );
        add_action( 'wp_ajax_aura_cert_bulk_step',      [ __CLASS__, 'ajax_bulk_step' ] );
        // Cron: procesa la cola de emisión masiva
        add_action( 'aura_certs_bulk_process',          [ __CLASS__, 'process_bulk_queue' ] );
    }

    /**
     * Genera el token HMAC para enlaces de descarga (correo / WhatsApp).
     */
    public static function build_download_token( string $folio, string $code ): string {
        return substr( hash_hmac( 'sha256', $folio . '|' . $code, wp_salt( 'auth' ) ), 0, 32 );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Emitir certificado individual (Estudiante o Usuario WP / Tercero)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_issue(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_issue' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para emitir certificados.', 'aura-suite' ) ], 403 );
        }

        $recipient_mode = sanitize_key( $_POST['recipient_mode'] ?? 'student' );
        $student_id     = absint( $_POST['student_id']    ?? 0 );
        $enrollment_id  = absint( $_POST['enrollment_id'] ?? 0 );
        $template_id    = absint( $_POST['template_id']   ?? 0 );

        $extra = [
            'description'        => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'course_name'        => sanitize_text_field( wp_unslash( $_POST['course_name'] ?? '' ) ),
            'program_name'       => sanitize_text_field( wp_unslash( $_POST['program_name'] ?? '' ) ),
            'instructor_name'    => sanitize_text_field( wp_unslash( $_POST['instructor_name'] ?? '' ) ),
            'graduation_date'    => sanitize_text_field( wp_unslash( $_POST['graduation_date'] ?? '' ) ),
            'hours'              => sanitize_text_field( wp_unslash( $_POST['hours'] ?? '' ) ),
            'grade'              => sanitize_text_field( wp_unslash( $_POST['grade'] ?? '' ) ),
            'category'           => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ),
            'city'               => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
            'include_signatures' => isset( $_POST['include_signatures'] ) ? (bool) absint( $_POST['include_signatures'] ) : (bool) Aura_Certificates_Settings::get( 'default_include_signatures', true ),
            'send_email'         => ! empty( $_POST['send_email'] ),
            'send_wa'            => ! empty( $_POST['send_whatsapp'] ),
        ];

        // Emisión para Usuario WP / Tercero / Manual (sin requerir inscripción)
        if ( $recipient_mode !== 'student' || ( $student_id === 0 && $enrollment_id === 0 ) ) {
            $payload = array_merge( $extra, [
                'recipient_type'     => sanitize_key( $_POST['recipient_type'] ?? 'wp_user' ),
                'wp_user_id'         => absint( $_POST['wp_user_id'] ?? 0 ),
                'third_party_id'     => absint( $_POST['third_party_id'] ?? 0 ),
                'student_id'         => $student_id,
                'enrollment_id'      => $enrollment_id,
                'recipient_name'     => sanitize_text_field( wp_unslash( $_POST['recipient_name'] ?? '' ) ),
                'recipient_email'    => sanitize_email( wp_unslash( $_POST['recipient_email'] ?? '' ) ),
                'recipient_document' => sanitize_text_field( wp_unslash( $_POST['recipient_document'] ?? '' ) ),
                'template_id'        => $template_id,
            ] );

            $result = self::issue_custom_certificate( $payload );

            if ( is_wp_error( $result ) ) {
                $status_code = $result->get_error_code() === 'validation_error' ? 400 : 500;
                wp_send_json_error( [ 'message' => $result->get_error_message() ], $status_code );
            }

            wp_send_json_success( [
                'certificate_id' => $result['certificate_id'],
                'folio'          => $result['folio'],
                'view_url'       => admin_url( 'admin.php?page=aura-certificates-list&action=view&id=' . $result['certificate_id'] ),
                'message'        => __( 'Certificado emitido correctamente.', 'aura-suite' ),
            ] );
            return;
        }

        if ( ! $student_id || ! $enrollment_id ) {
            wp_send_json_error( [ 'message' => __( 'Estudiante o inscripción no especificados.', 'aura-suite' ) ], 400 );
        }

        // Verificar paz y salvo si está configurado
        if ( Aura_Certificates_Settings::get( 'require_paz_salvo', false ) ) {
            $has_paz_salvo = apply_filters( 'aura_student_has_paz_salvo', false, $student_id );
            if ( ! $has_paz_salvo ) {
                wp_send_json_error( [
                    'message' => __( 'El estudiante no cuenta con paz y salvo financiero.', 'aura-suite' ),
                    'code'    => 'no_paz_salvo',
                ], 409 );
            }
        }

        // Verificar que no tenga ya un certificado activo para esta inscripción
        global $wpdb;
        $certs_table = $wpdb->prefix . 'aura_certificates';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $already = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$certs_table} WHERE enrollment_id = %d AND status = 'active'",
                $enrollment_id
            )
        );

        if ( $already > 0 ) {
            wp_send_json_error( [ 'message' => __( 'Ya existe un certificado activo para esta inscripción.', 'aura-suite' ), 'code' => 'duplicate' ], 409 );
        }

        $result = self::issue_certificate( $student_id, $enrollment_id, $template_id, $extra );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
        }

        wp_send_json_success( [
            'certificate_id' => $result['certificate_id'],
            'folio'          => $result['folio'],
            'view_url'       => admin_url( 'admin.php?page=aura-certificates-list&action=view&id=' . $result['certificate_id'] ),
            'message'        => __( 'Certificado emitido correctamente.', 'aura-suite' ),
        ] );
    }

    /**
     * AJAX: Buscar estudiantes e inscripciones para el formulario de emisión.
     */
    public static function ajax_search_students(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_issue' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $term           = sanitize_text_field( wp_unslash( $_REQUEST['term'] ?? '' ) );
        $students_table = $wpdb->prefix . 'aura_students';
        $enroll_table   = $wpdb->prefix . 'aura_student_enrollments';
        $courses_table  = $wpdb->prefix . 'aura_student_courses';
        $certs_table    = $wpdb->prefix . 'aura_certificates';

        $where  = '1=1';
        $params = [];
        if ( $term !== '' ) {
            $like   = '%' . $wpdb->esc_like( $term ) . '%';
            $where .= ' AND (s.first_name LIKE %s OR s.last_name LIKE %s OR s.email LIKE %s OR c.name LIKE %s)';
            $params = [ $like, $like, $like, $like ];
        }

        $sql = "SELECT e.id AS enrollment_id, s.id AS student_id, s.first_name, s.last_name, s.email, s.wp_user_id,
                       COALESCE(c.name, 'Programa General') AS course_name,
                       COALESCE(c.name, 'Programa General') AS program_name,
                       COALESCE(s.graduated_at, e.updated_at) AS graduation_date,
                       e.status AS enrollment_status, s.status AS student_status,
                       (SELECT COUNT(*) FROM {$certs_table} cert WHERE cert.enrollment_id = e.id AND cert.status = 'active') AS has_cert
                FROM {$enroll_table} e
                INNER JOIN {$students_table} s ON e.student_id = s.id
                LEFT JOIN {$courses_table} c ON c.id = e.course_id
                WHERE {$where}
                ORDER BY has_cert ASC, e.id DESC
                LIMIT 30";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) : $wpdb->get_results( $sql );

        wp_send_json_success( [ 'items' => $rows ?: [] ] );
    }

    /**
     * AJAX: Reenviar correo electrónico de un certificado existente.
     */
    public static function ajax_resend_email(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_issue' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        $cert_id = absint( $_POST['id'] ?? 0 );
        if ( ! $cert_id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ], 400 );
        }

        if ( class_exists( 'Aura_Certificates_Notifications' ) ) {
            Aura_Certificates_Notifications::send_issued_email( $cert_id );
        }

        wp_send_json_success( [ 'message' => __( 'Notificación reenviada al destinatario.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // LÓGICA CENTRAL DE EMISIÓN
    // ─────────────────────────────────────────────────────────────

    /**
     * Emite un certificado vinculado a un estudiante + inscripción.
     *
     * @param int   $student_id
     * @param int   $enrollment_id
     * @param int   $template_id  0 = usar plantilla por defecto
     * @param array $extra        ['description', 'send_email', 'send_wa', ...]
     * @return array|WP_Error ['certificate_id' => int, 'folio' => string, 'pdf_path' => string]
     */
    public static function issue_certificate( int $student_id, int $enrollment_id, int $template_id = 0, array $extra = [] ): array|\WP_Error {
        global $wpdb;

        $students_table    = $wpdb->prefix . 'aura_students';
        $enrollments_table = $wpdb->prefix . 'aura_student_enrollments';
        $courses_table     = $wpdb->prefix . 'aura_student_courses';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$students_table} WHERE id = %d", $student_id ) );
        if ( ! $student ) {
            return new \WP_Error( 'student_not_found', __( 'Estudiante no encontrado.', 'aura-suite' ) );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $enrollment = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.*, c.name as course_name, c.name as program_name
             FROM {$enrollments_table} e
             LEFT JOIN {$courses_table} c ON c.id = e.course_id
             WHERE e.id = %d AND e.student_id = %d",
            $enrollment_id, $student_id
        ) );
        if ( ! $enrollment ) {
            return new \WP_Error( 'enrollment_not_found', __( 'Inscripción no encontrada.', 'aura-suite' ) );
        }
        if ( empty( $enrollment->graduation_date ) && ! empty( $student->graduated_at ) ) {
            $enrollment->graduation_date = $student->graduated_at;
        }

        $org_name = Aura_Certificates_Settings::get( 'org_name', '' ) ?: get_option( 'blogname', '' );
        $full_name = trim( ( $student->first_name ?? '' ) . ' ' . ( $student->last_name ?? '' ) );

        $recipient_email = '';
        if ( ! empty( $student->wp_user_id ) ) {
            $wp_u = get_user_by( 'id', (int) $student->wp_user_id );
            if ( $wp_u ) {
                $recipient_email = $wp_u->user_email;
            }
        }
        if ( ! $recipient_email && ! empty( $student->email ) ) {
            $recipient_email = $student->email;
        }

        $record_data = [
            'student_id'         => $student_id,
            'enrollment_id'      => $enrollment_id,
            'recipient_type'     => 'student',
            'wp_user_id'         => ! empty( $student->wp_user_id ) ? (int) $student->wp_user_id : null,
            'third_party_id'     => null,
            'recipient_email'    => $recipient_email,
            'recipient_document' => $student->document_id ?? ( $student->identification ?? '' ),
            'student_name'       => $full_name,
            'course_name'        => ! empty( $extra['course_name'] ) ? $extra['course_name'] : ( $enrollment->course_name ?? '' ),
            'program_name'       => ! empty( $extra['program_name'] ) ? $extra['program_name'] : ( $enrollment->program_name ?? '' ),
            'instructor_name'    => ! empty( $extra['instructor_name'] ) ? $extra['instructor_name'] : ( $enrollment->instructor_name ?? '' ),
            'organization_name'  => $org_name,
            'graduation_date'    => ! empty( $extra['graduation_date'] ) ? $extra['graduation_date'] : ( $enrollment->graduation_date ?? '' ),
            'hours'              => $extra['hours'] ?? '',
            'grade'              => $extra['grade'] ?? '',
            'category'           => $extra['category'] ?? '',
            'city'               => $extra['city'] ?? '',
        ];

        $vars_data = [
            'first_name'           => $student->first_name ?? '',
            'last_name'            => $student->last_name  ?? '',
            'full_name'            => $full_name,
            'document_id'          => $record_data['recipient_document'],
            'email'                => $recipient_email,
            'recipient_type_label' => __( 'Estudiante', 'aura-suite' ),
            'course_name'          => $record_data['course_name'],
            'program_name'         => $record_data['program_name'],
            'category'             => $record_data['category'],
            'hours'                => $record_data['hours'],
            'grade'                => $record_data['grade'],
            'city'                 => $record_data['city'],
            'issued_at'            => current_time( 'mysql' ),
            'graduation_date'      => $record_data['graduation_date'],
            'instructor_name'      => $record_data['instructor_name'],
            'organization_name'    => $org_name,
            'description'          => $extra['description'] ?? '',
            'student_photo_url'    => ! empty( $extra['student_photo_url'] )
                                        ? (string) $extra['student_photo_url']
                                        : (string) ( $student->photo_url ?? '' ),
        ];

        $result = self::create_and_store_certificate( $record_data, $vars_data, $template_id, $extra );

        if ( ! is_wp_error( $result ) ) {
            delete_transient( 'aura_cert_ready_' . $student_id );
            do_action( 'aura_certificate_issued', $result['certificate_id'], $student_id, $enrollment_id );
        }

        return $result;
    }

    /**
     * Emite un certificado para un Usuario WP, Tercero o destinatario manual.
     */
    public static function issue_custom_certificate( array $payload ): array|\WP_Error {
        $wp_user_id     = absint( $payload['wp_user_id'] ?? 0 );
        $third_party_id = absint( $payload['third_party_id'] ?? 0 );
        $student_id     = absint( $payload['student_id'] ?? 0 );
        $enrollment_id  = absint( $payload['enrollment_id'] ?? 0 );

        $full_name  = sanitize_text_field( $payload['recipient_name'] ?? ( $payload['full_name'] ?? '' ) );
        $first_name = '';
        $last_name  = '';
        $email      = sanitize_email( $payload['recipient_email'] ?? ( $payload['email'] ?? '' ) );
        $doc_id     = sanitize_text_field( $payload['recipient_document'] ?? ( $payload['document_id'] ?? '' ) );
        $rtype      = 'manual';
        $rtype_lbl  = __( 'Destinatario', 'aura-suite' );

        if ( $wp_user_id > 0 ) {
            $user = get_user_by( 'id', $wp_user_id );
            if ( $user ) {
                $rtype      = 'wp_user';
                $rtype_lbl  = __( 'Usuario WP', 'aura-suite' );
                $first_name = $user->first_name ?: '';
                $last_name  = $user->last_name ?: '';
                if ( $full_name === '' ) {
                    $full_name = trim( $first_name . ' ' . $last_name ) ?: $user->display_name;
                }
                if ( $email === '' ) {
                    $email = $user->user_email;
                }
            }
        } elseif ( $third_party_id > 0 && class_exists( 'Aura_Third_Parties' ) ) {
            $tp = Aura_Third_Parties::get_third_party( $third_party_id );
            if ( $tp ) {
                $rtype     = 'third_party';
                $rtype_lbl = __( 'Tercero / Aliado', 'aura-suite' );
                if ( $full_name === '' ) {
                    $full_name = $tp->commercial_name ?: $tp->full_name;
                }
                if ( $email === '' ) {
                    $email = $tp->email ?: ( $tp->wp_user_email ?? '' );
                }
                if ( $doc_id === '' ) {
                    $doc_id = $tp->document_id ?? '';
                }
                if ( ! empty( $tp->wp_user_id ) ) {
                    $wp_user_id = (int) $tp->wp_user_id;
                }
            }
        }

        if ( $full_name === '' ) {
            return new \WP_Error( 'validation_error', __( 'Debe seleccionar o escribir el nombre del destinatario.', 'aura-suite' ) );
        }

        $course_name = sanitize_text_field( $payload['course_name'] ?? '' );
        if ( $course_name === '' ) {
            return new \WP_Error( 'validation_error', __( 'El nombre del curso o reconocimiento es obligatorio.', 'aura-suite' ) );
        }

        if ( $first_name === '' && $full_name !== '' ) {
            $parts      = preg_split( '/\s+/', trim( $full_name ), 2 );
            $first_name = $parts[0] ?? '';
            $last_name  = $parts[1] ?? '';
        }

        $org_name = Aura_Certificates_Settings::get( 'org_name', '' ) ?: get_option( 'blogname', '' );

        $record_data = [
            'student_id'         => $student_id,
            'enrollment_id'      => $enrollment_id > 0 ? $enrollment_id : null,
            'recipient_type'     => $rtype,
            'wp_user_id'         => $wp_user_id > 0 ? $wp_user_id : null,
            'third_party_id'     => $third_party_id > 0 ? $third_party_id : null,
            'recipient_email'    => $email,
            'recipient_document' => $doc_id,
            'student_name'       => $full_name,
            'course_name'        => $course_name,
            'program_name'       => sanitize_text_field( $payload['program_name'] ?? '' ),
            'instructor_name'    => sanitize_text_field( $payload['instructor_name'] ?? '' ),
            'organization_name'  => $org_name,
            'graduation_date'    => sanitize_text_field( $payload['graduation_date'] ?? '' ),
            'hours'              => sanitize_text_field( $payload['hours'] ?? '' ),
            'grade'              => sanitize_text_field( $payload['grade'] ?? '' ),
            'category'           => sanitize_text_field( $payload['category'] ?? '' ),
            'city'               => sanitize_text_field( $payload['city'] ?? '' ),
        ];

        $vars_data = [
            'first_name'           => $first_name,
            'last_name'            => $last_name,
            'full_name'            => $full_name,
            'document_id'          => $doc_id,
            'email'                => $email,
            'recipient_type_label' => $rtype_lbl,
            'course_name'          => $record_data['course_name'],
            'program_name'         => $record_data['program_name'],
            'category'             => $record_data['category'],
            'hours'                => $record_data['hours'],
            'grade'                => $record_data['grade'],
            'city'                 => $record_data['city'],
            'issued_at'            => current_time( 'mysql' ),
            'graduation_date'      => $record_data['graduation_date'],
            'instructor_name'      => $record_data['instructor_name'],
            'organization_name'    => $org_name,
            'description'          => $payload['description'] ?? '',
        ];

        $result = self::create_and_store_certificate(
            $record_data,
            $vars_data,
            absint( $payload['template_id'] ?? 0 ),
            $payload
        );

        if ( ! is_wp_error( $result ) ) {
            do_action( 'aura_certificate_issued', $result['certificate_id'], $student_id, $enrollment_id );
        }

        return $result;
    }

    /**
     * Núcleo compartido para generar folio, QR, PDF, guardar en BD y notificar.
     */
    private static function create_and_store_certificate( array $record_data, array $vars_data, int $template_id, array $extra ): array|\WP_Error {
        global $wpdb;

        if ( $template_id > 0 ) {
            $templates_table = $wpdb->prefix . 'aura_certificate_templates';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $template = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$templates_table} WHERE id = %d AND is_active = 1", $template_id ) );
        } else {
            $template = Aura_Certificates_Templates::get_default_template();
        }

        if ( ! $template ) {
            return new \WP_Error( 'no_template', __( 'No hay plantilla de certificado disponible. Configure una plantilla predeterminada.', 'aura-suite' ) );
        }

        $folio       = Aura_Certificates_Folio::generate();
        $uuid        = wp_generate_uuid4();
        $short_code  = strtoupper( substr( str_replace( '-', '', $uuid ), 0, 8 ) );
        $verify_slug = Aura_Certificates_Settings::get( 'verify_slug', 'verificar-certificado' );
        $verify_url  = trailingslashit( home_url() ) . $verify_slug . '/' . $folio;

        $qr_path = self::generate_qr( $folio, $verify_url );

        $include_signers = isset( $extra['include_signatures'] )
            ? (bool) $extra['include_signatures']
            : (bool) Aura_Certificates_Settings::get( 'default_include_signatures', true );
        $signers = $include_signers && class_exists( 'Aura_Certificates_Signers' )
            ? Aura_Certificates_Signers::get_active_signers_for_js()
            : [];

        $vars_data['folio']             = $folio;
        $vars_data['verification_code'] = $short_code;
        $vars_data['verify_url']        = $verify_url;
        $vars_data['signers']           = $signers;

        if ( empty( $vars_data['student_photo_url'] ) ) {
            $vars_data['student_photo_url'] = self::resolve_recipient_photo_url(
                absint( $record_data['student_id'] ?? 0 ),
                absint( $record_data['wp_user_id'] ?? 0 ),
                (string) ( $extra['student_photo_url'] ?? '' )
            );
        }

        $pdf_result = self::generate_pdf( $template, $vars_data, $folio, $qr_path );
        if ( is_wp_error( $pdf_result ) ) {
            return $pdf_result;
        }

        $certs_table = $wpdb->prefix . 'aura_certificates';
        $grad_date   = ! empty( $record_data['graduation_date'] ) ? date( 'Y-m-d', strtotime( $record_data['graduation_date'] ) ) : null;

        $inserted = $wpdb->insert(
            $certs_table,
            [
                'student_id'         => absint( $record_data['student_id'] ?? 0 ),
                'enrollment_id'      => ! empty( $record_data['enrollment_id'] ) ? absint( $record_data['enrollment_id'] ) : null,
                'recipient_type'     => $record_data['recipient_type'] ?? 'student',
                'wp_user_id'         => ! empty( $record_data['wp_user_id'] ) ? absint( $record_data['wp_user_id'] ) : null,
                'third_party_id'     => ! empty( $record_data['third_party_id'] ) ? absint( $record_data['third_party_id'] ) : null,
                'recipient_email'    => $record_data['recipient_email'] ?? '',
                'recipient_document' => $record_data['recipient_document'] ?? '',
                'template_id'        => (int) $template->id,
                'folio'              => $folio,
                'uuid'               => $uuid,
                'verification_code'  => $uuid,
                'student_name'       => $record_data['student_name'] ?? '',
                'course_name'        => $record_data['course_name'] ?? '',
                'program_name'       => $record_data['program_name'] ?? '',
                'category'           => $record_data['category'] ?? '',
                'hours'              => $record_data['hours'] ?? '',
                'grade'              => $record_data['grade'] ?? '',
                'city'               => $record_data['city'] ?? '',
                'instructor_name'    => $record_data['instructor_name'] ?? '',
                'organization_name'  => $record_data['organization_name'] ?? '',
                'graduation_date'    => $grad_date,
                'issued_by'          => get_current_user_id(),
                'issued_at'          => current_time( 'mysql' ),
                'pdf_path'           => $pdf_result['pdf_path'],
                'qr_path'            => $qr_path ?? '',
                'verify_url'         => $verify_url,
                'include_signatures' => $include_signers ? 1 : 0,
                'signers_json'       => wp_json_encode( $signers ),
                'status'             => 'active',
                'description'        => $extra['description'] ?? '',
                'send_email'         => (int) ! empty( $extra['send_email'] ),
                'send_whatsapp'      => (int) ! empty( $extra['send_wa'] ),
            ]
        );

        if ( ! $inserted || ! $wpdb->insert_id ) {
            return new \WP_Error( 'db_error', __( 'Error al guardar el certificado en la base de datos.', 'aura-suite' ) );
        }

        $cert_id = (int) $wpdb->insert_id;

        if ( ! empty( $record_data['wp_user_id'] ) ) {
            $user = get_user_by( 'id', (int) $record_data['wp_user_id'] );
            if ( $user ) {
                $user->add_cap( 'aura_cert_download_own' );
            }
        }

        if ( class_exists( 'Aura_Certificates_Notifications' ) ) {
            if ( ! empty( $extra['send_email'] ) ) {
                Aura_Certificates_Notifications::send_issued_email( $cert_id );
            }
            if ( ! empty( $extra['send_wa'] ) ) {
                Aura_Certificates_Notifications::send_issued_whatsapp( $cert_id );
            }
        }

        return [
            'certificate_id' => $cert_id,
            'folio'          => $folio,
            'pdf_path'       => $pdf_result['pdf_path'],
            'pdf_url'        => $pdf_result['pdf_url'],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Revocar certificado
    // ─────────────────────────────────────────────────────────────

    public static function ajax_revoke(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_revoke' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para revocar certificados.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table  = $wpdb->prefix . 'aura_certificates';
        $id     = absint( $_POST['id'] ?? 0 );
        $folio  = sanitize_text_field( wp_unslash( $_POST['folio'] ?? '' ) );
        $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

        if ( ! $id && ! $folio ) {
            wp_send_json_error( [ 'message' => __( 'ID o folio inválido.', 'aura-suite' ) ], 400 );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $cert = $id
            ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) )
            : $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE folio = %s", $folio ) );

        if ( ! $cert ) {
            wp_send_json_error( [ 'message' => __( 'Certificado no encontrado.', 'aura-suite' ) ], 404 );
        }

        $wpdb->update(
            $table,
            [
                'status'        => 'revoked',
                'revoke_reason' => $reason,
                'revoked_at'    => current_time( 'mysql' ),
                'revoked_by'    => get_current_user_id(),
            ],
            [ 'id' => (int) $cert->id ],
            [ '%s', '%s', '%s', '%d' ],
            [ '%d' ]
        );

        if ( class_exists( 'Aura_Certificates_Notifications' ) ) {
            Aura_Certificates_Notifications::send_revoke_email( (int) $cert->id );
        }

        do_action( 'aura_certificate_revoked', (int) $cert->id, $reason );

        wp_send_json_success( [ 'message' => __( 'Certificado revocado.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Descarga segura de PDF
    // ─────────────────────────────────────────────────────────────

    public static function ajax_download(): void {
        $folio = sanitize_text_field( wp_unslash( $_REQUEST['folio'] ?? '' ) );
        $token = sanitize_text_field( wp_unslash( $_REQUEST['token'] ?? '' ) );
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ?? '' ) );

        if ( empty( $folio ) || ! Aura_Certificates_Folio::is_valid_format( $folio ) ) {
            wp_die( esc_html__( 'Folio inválido.', 'aura-suite' ), 400 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificates';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $cert  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE folio = %s", $folio ) );

        if ( ! $cert ) {
            wp_die( esc_html__( 'Certificado no encontrado.', 'aura-suite' ), 404 );
        }

        if ( $cert->status !== 'active' ) {
            wp_die( esc_html__( 'Este certificado ha sido revocado.', 'aura-suite' ), 410 );
        }

        $authorized = current_user_can( 'aura_cert_download_any' ) || current_user_can( 'manage_options' );

        if ( ! $authorized && $token !== '' ) {
            $code = (string) ( $cert->uuid ?: $cert->verification_code ?: '' );
            if ( $code !== '' && hash_equals( self::build_download_token( $folio, $code ), $token ) ) {
                $authorized = true;
            }
        }

        if ( ! $authorized ) {
            $current_user = wp_get_current_user();
            if ( $current_user->ID ) {
                if ( ! empty( $cert->wp_user_id ) && (int) $cert->wp_user_id === (int) $current_user->ID ) {
                    $authorized = true;
                } elseif ( ! empty( $cert->student_id ) ) {
                    $students_table = $wpdb->prefix . 'aura_students';
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $student = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$students_table} WHERE wp_user_id = %d", $current_user->ID ) );
                    if ( $student && (int) $student->id === (int) $cert->student_id ) {
                        $authorized = true;
                    }
                }
            }
            if ( $authorized && $nonce !== '' && ! wp_verify_nonce( $nonce, 'aura_download_' . $folio ) ) {
                // Si viene autenticado y dueño, igual permitir salvo nonce manipulado en formulario
                $authorized = true;
            }
        }

        if ( ! $authorized ) {
            wp_die( esc_html__( 'No tiene permiso para descargar este certificado.', 'aura-suite' ), 403 );
        }

        $pdf_path = $cert->pdf_path;

        if ( empty( $pdf_path ) || ! file_exists( $pdf_path ) ) {
            wp_die( esc_html__( 'El archivo PDF no está disponible.', 'aura-suite' ), 404 );
        }

        // Enviar el archivo
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="certificado-' . sanitize_file_name( $folio ) . '.pdf"' );
        header( 'Content-Length: ' . filesize( $pdf_path ) );
        header( 'Cache-Control: private, no-cache' );
        header( 'X-Content-Type-Options: nosniff' );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        readfile( $pdf_path );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // EMISIÓN MASIVA
    // ─────────────────────────────────────────────────────────────

    /**
     * Encola certificados para emisión masiva (Estudiantes graduados O Usuarios WP / Terceros).
     */
    public static function ajax_queue_bulk(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_issue' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        $items_raw = $_POST['items'] ?? [];
        if ( is_string( $items_raw ) ) {
            $decoded_items = json_decode( wp_unslash( $items_raw ), true );
            $items_raw     = is_array( $decoded_items ) ? $decoded_items : [];
        }

        $template_id = absint( $_POST['template_id'] ?? 0 );
        $shared_meta = [
            'course_name'        => sanitize_text_field( wp_unslash( $_POST['course_name'] ?? '' ) ),
            'program_name'       => sanitize_text_field( wp_unslash( $_POST['program_name'] ?? '' ) ),
            'instructor_name'    => sanitize_text_field( wp_unslash( $_POST['instructor_name'] ?? '' ) ),
            'graduation_date'    => sanitize_text_field( wp_unslash( $_POST['graduation_date'] ?? '' ) ),
            'hours'              => sanitize_text_field( wp_unslash( $_POST['hours'] ?? '' ) ),
            'grade'              => sanitize_text_field( wp_unslash( $_POST['grade'] ?? '' ) ),
            'category'           => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ),
            'city'               => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
            'description'        => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'send_email'         => ! empty( $_POST['send_email'] ),
            'send_wa'            => ! empty( $_POST['send_whatsapp'] ),
            'include_signatures' => isset( $_POST['include_signatures'] ) ? (bool) absint( $_POST['include_signatures'] ) : true,
        ];

        if ( ! is_array( $items_raw ) || empty( $items_raw ) ) {
            wp_send_json_error( [ 'message' => __( 'No hay destinatarios seleccionados.', 'aura-suite' ) ], 400 );
        }

        $items = [];
        foreach ( $items_raw as $item ) {
            if ( ! is_array( $item ) ) continue;
            $student_id     = absint( $item['student_id'] ?? 0 );
            $enrollment_id  = absint( $item['enrollment_id'] ?? 0 );
            $wp_user_id     = absint( $item['wp_user_id'] ?? 0 );
            $third_party_id = absint( $item['third_party_id'] ?? 0 );
            $name           = sanitize_text_field( $item['name'] ?? '' );

            if ( $student_id > 0 && $enrollment_id > 0 ) {
                $items[] = [
                    'mode'          => 'student',
                    'student_id'    => $student_id,
                    'enrollment_id' => $enrollment_id,
                ];
            } elseif ( $wp_user_id > 0 || $third_party_id > 0 || $name !== '' ) {
                $items[] = [
                    'mode'           => 'custom',
                    'recipient_type' => $wp_user_id > 0 ? 'wp_user' : ( $third_party_id > 0 ? 'third_party' : 'manual' ),
                    'wp_user_id'     => $wp_user_id,
                    'third_party_id' => $third_party_id,
                    'recipient_name' => $name,
                    'recipient_email'=> sanitize_email( $item['email'] ?? '' ),
                    'recipient_document' => sanitize_text_field( $item['document_id'] ?? '' ),
                ];
            }
        }

        if ( empty( $items ) ) {
            wp_send_json_error( [ 'message' => __( 'Los datos de los destinatarios no son válidos.', 'aura-suite' ) ], 400 );
        }

        $has_custom = false;
        foreach ( $items as $it ) {
            if ( $it['mode'] === 'custom' ) {
                $has_custom = true;
                break;
            }
        }
        if ( $has_custom && $shared_meta['course_name'] === '' ) {
            wp_send_json_error( [ 'message' => __( 'Ingresa el nombre del curso o evento para la emisión masiva de Usuarios/Terceros.', 'aura-suite' ) ], 400 );
        }

        $batch_id = wp_generate_uuid4();

        $queue = [
            'batch_id'    => $batch_id,
            'template_id' => $template_id,
            'shared_meta' => $shared_meta,
            'items'       => array_values( $items ),
            'total'       => count( $items ),
            'processed'   => 0,
            'success'     => 0,
            'failed'      => 0,
            'status'      => 'pending',
            'created_at'  => time(),
            'created_by'  => get_current_user_id(),
        ];

        set_transient( 'aura_cert_bulk_' . $batch_id, $queue, DAY_IN_SECONDS );

        if ( ! wp_next_scheduled( 'aura_certs_bulk_process' ) ) {
            wp_schedule_single_event( time() + 5, 'aura_certs_bulk_process' );
        }

        wp_send_json_success( [
            'batch_id' => $batch_id,
            'total'    => count( $items ),
            'message'  => __( 'Procesamiento masivo iniciado.', 'aura-suite' ),
        ] );
    }

    /**
     * Consulta el estado de un batch de emisión masiva.
     */
    public static function ajax_bulk_status(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        $batch_id = sanitize_text_field( wp_unslash( $_POST['batch_id'] ?? '' ) );

        if ( empty( $batch_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Batch ID inválido.', 'aura-suite' ) ], 400 );
        }

        $queue = get_transient( 'aura_cert_bulk_' . $batch_id );

        if ( $queue === false ) {
            wp_send_json_error( [ 'message' => __( 'Batch no encontrado o expirado.', 'aura-suite' ) ], 404 );
        }

        $percent = $queue['total'] > 0 ? round( ( $queue['processed'] / $queue['total'] ) * 100 ) : 0;

        wp_send_json_success( [
            'batch_id'   => $batch_id,
            'status'     => $queue['status'],
            'total'      => $queue['total'],
            'processed'  => $queue['processed'],
            'done'       => $queue['processed'],
            'success'    => $queue['success'],
            'failed'     => $queue['failed'],
            'percent'    => $percent,
        ] );
    }

    /**
     * Ejecuta un bloque de emisión masiva directamente vía AJAX para progreso en tiempo real.
     */
    public static function ajax_bulk_step(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_issue' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        $batch_id = sanitize_text_field( wp_unslash( $_POST['batch_id'] ?? '' ) );
        if ( empty( $batch_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Batch ID inválido.', 'aura-suite' ) ], 400 );
        }

        $queue = self::process_single_batch_queue( $batch_id, 5 );
        if ( ! $queue ) {
            wp_send_json_error( [ 'message' => __( 'Batch no encontrado o expirado.', 'aura-suite' ) ], 404 );
        }

        $percent = $queue['total'] > 0 ? round( ( $queue['processed'] / $queue['total'] ) * 100 ) : 100;

        wp_send_json_success( [
            'batch_id'  => $batch_id,
            'status'    => $queue['status'],
            'total'     => $queue['total'],
            'processed' => $queue['processed'],
            'done'      => $queue['processed'],
            'success'   => $queue['success'],
            'failed'    => $queue['failed'],
            'percent'   => $percent,
        ] );
    }

    /**
     * Procesa hasta $chunk_size ítems de un batch específico.
     */
    private static function process_single_batch_queue( string $batch_id, int $chunk_size = 10 ): ?array {
        $queue = get_transient( 'aura_cert_bulk_' . $batch_id );
        if ( ! $queue || ! is_array( $queue ) ) {
            return null;
        }
        if ( $queue['status'] === 'completed' ) {
            return $queue;
        }

        $queue['status'] = 'processing';
        $pending         = array_slice( $queue['items'], $queue['processed'], $chunk_size );
        $shared          = $queue['shared_meta'] ?? [];

        foreach ( $pending as $item ) {
            if ( ( $item['mode'] ?? 'student' ) === 'custom' ) {
                $result = self::issue_custom_certificate( array_merge( $shared, [
                    'recipient_type'     => $item['recipient_type'] ?? 'manual',
                    'wp_user_id'         => $item['wp_user_id'] ?? 0,
                    'third_party_id'     => $item['third_party_id'] ?? 0,
                    'recipient_name'     => $item['recipient_name'] ?? '',
                    'recipient_email'    => $item['recipient_email'] ?? '',
                    'recipient_document' => $item['recipient_document'] ?? '',
                    'template_id'        => $queue['template_id'],
                ] ) );
            } else {
                $result = self::issue_certificate(
                    absint( $item['student_id'] ?? 0 ),
                    absint( $item['enrollment_id'] ?? 0 ),
                    absint( $queue['template_id'] ?? 0 ),
                    $shared
                );
            }

            $queue['processed']++;
            if ( is_wp_error( $result ) ) {
                $queue['failed']++;
            } else {
                $queue['success']++;
            }
        }

        if ( $queue['processed'] >= $queue['total'] ) {
            $queue['status'] = 'completed';

            if ( class_exists( 'Aura_Certificates_Notifications' ) ) {
                Aura_Certificates_Notifications::send_bulk_complete_email(
                    (int) ( $queue['created_by'] ?? 0 ),
                    [
                        'total'   => $queue['total'],
                        'success' => $queue['success'],
                        'failed'  => $queue['failed'],
                    ]
                );
            }
        }

        set_transient( 'aura_cert_bulk_' . $batch_id, $queue, DAY_IN_SECONDS );
        return $queue;
    }

    /**
     * Procesador de la cola masiva (llamado por el cron).
     */
    public static function process_bulk_queue(): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $batches = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_aura_cert_bulk_%'
             LIMIT 5"
        );

        if ( empty( $batches ) ) {
            return;
        }

        foreach ( $batches as $opt_name ) {
            $batch_id = str_replace( '_transient_aura_cert_bulk_', '', $opt_name );
            $queue    = self::process_single_batch_queue( $batch_id, 10 );

            if ( $queue && $queue['status'] !== 'completed' && ! wp_next_scheduled( 'aura_certs_bulk_process' ) ) {
                wp_schedule_single_event( time() + 120, 'aura_certs_bulk_process' );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // GENERACIÓN DE QR
    // ─────────────────────────────────────────────────────────────

    /**
     * Genera la imagen QR para la URL de verificación.
     *
     * @param string $folio
     * @param string $url URL completa de verificación.
     * @return string|null Ruta absoluta al PNG del QR, o null si no se pudo generar.
     */
    private static function generate_qr( string $folio, string $url ): ?string {
        if ( ! class_exists( '\chillerlan\QRCode\QRCode' ) ) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        $dir        = $upload_dir['basedir'] . '/aura-certificates/qr';
        wp_mkdir_p( $dir );

        // Proteger directorio
        $htaccess = $dir . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "Options -Indexes\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        }

        $filename = 'qr-' . sanitize_file_name( $folio ) . '.png';
        $filepath = $dir . '/' . $filename;

        try {
            $options                = new \chillerlan\QRCode\QROptions();
            $options->outputType    = \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG;
            $options->eccLevel      = \chillerlan\QRCode\QRCode::ECC_H;
            $options->imageBase64   = false;
            $options->scale         = 6;

            $qr = new \chillerlan\QRCode\QRCode( $options );
            $qr->render( $url, $filepath );
        } catch ( \Exception $e ) {
            return null;
        }

        return $filepath;
    }

    // ─────────────────────────────────────────────────────────────
    // GENERACIÓN DE PDF
    // ─────────────────────────────────────────────────────────────

    /**
     * Genera el PDF del certificado usando mPDF.
     *
     * @param object $template   Fila de la plantilla de BD.
     * @param array  $vars_data  Variables para sustituir en el diseño.
     * @param string $folio      Folio del certificado.
     * @param string|null $qr_path Ruta al QR generado.
     * @return array|WP_Error ['pdf_path' => string, 'pdf_url' => string]
     */
    /**
     * Devuelve dimensiones en mm y orientación mPDF según el valor de orientation.
     *
     * Valores soportados:
     *   landscape         → A4  Horizontal  (297 × 210 mm)
     *   portrait          → A4  Vertical    (210 × 297 mm)
     *   letter_landscape  → Carta Horizontal (279.4 × 215.9 mm)
     *   letter_portrait   → Carta Vertical   (215.9 × 279.4 mm)
     *
     * @param string $orientation
     * @return array{w_mm:float, h_mm:float, mpdf_orient: string}
     */
    private static function get_paper_dimensions( string $orientation ): array {
        switch ( $orientation ) {
            case 'portrait':
                return [ 'w_mm' => 210.0, 'h_mm' => 297.0, 'mpdf_orient' => 'P' ];
            case 'letter_landscape':
                return [ 'w_mm' => 279.4, 'h_mm' => 215.9, 'mpdf_orient' => 'L' ];
            case 'letter_portrait':
                return [ 'w_mm' => 215.9, 'h_mm' => 279.4, 'mpdf_orient' => 'P' ];
            case 'landscape':
            default:
                return [ 'w_mm' => 297.0, 'h_mm' => 210.0, 'mpdf_orient' => 'L' ];
        }
    }

    private static function generate_pdf( object $template, array $vars_data, string $folio, ?string $qr_path ): array|\WP_Error {
        if ( ! class_exists( '\Mpdf\Mpdf' ) ) {
            return new \WP_Error( 'mpdf_missing', __( 'La librería mPDF no está disponible.', 'aura-suite' ) );
        }

        // Directorio de destino
        $upload_dir     = wp_upload_dir();
        $year           = date( 'Y' );
        $month          = date( 'm' );
        $dest_dir       = $upload_dir['basedir'] . '/aura-certificates/' . $year . '/' . $month;
        $dest_url_base  = $upload_dir['baseurl'] . '/aura-certificates/' . $year . '/' . $month;

        if ( ! wp_mkdir_p( $dest_dir ) ) {
            return new \WP_Error( 'dir_error', __( 'No se pudo crear el directorio para el PDF.', 'aura-suite' ) );
        }

        // Proteger el directorio aura-certificates raíz con .htaccess
        $root_dir       = $upload_dir['basedir'] . '/aura-certificates';
        $htaccess_path  = $root_dir . '/.htaccess';
        if ( ! file_exists( $htaccess_path ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents( $htaccess_path, "Deny from all\n" );
        }
        $index_path = $root_dir . '/index.php';
        if ( ! file_exists( $index_path ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents( $index_path, "<?php // Silence is golden.\n" );
        }

        // Orientación y tamaño del papel
        $dim = self::get_paper_dimensions( $template->orientation ?? 'landscape' );

        try {
            $dpi  = (int) Aura_Certificates_Settings::get( 'pdf_dpi', 150 );
            $mpdf = new \Mpdf\Mpdf( [
                'format'        => [ $dim['w_mm'], $dim['h_mm'] ],
                'orientation'   => $dim['mpdf_orient'],
                'margin_top'    => 0,
                'margin_bottom' => 0,
                'margin_left'   => 0,
                'margin_right'  => 0,
                'dpi'           => $dpi,
                'img_dpi'       => $dpi,
            ] );

            // Construir HTML a partir del JSON del canvas
            $html = self::canvas_json_to_html( $template->design_json, $vars_data, $qr_path );

            $mpdf->WriteHTML( $html );

            $filename = 'cert-' . sanitize_file_name( $folio ) . '.pdf';
            $filepath = $dest_dir . '/' . $filename;

            $mpdf->Output( $filepath, 'F' );

        } catch ( \Exception $e ) {
            return new \WP_Error( 'mpdf_error', $e->getMessage() );
        }

        return [
            'pdf_path' => $filepath,
            'pdf_url'  => $dest_url_base . '/' . $filename,
        ];
    }

    /**
     * Convierte el JSON de Fabric.js en HTML simple para mPDF.
     * Las imágenes (background, firmas, logo) se insertan como base64.
     *
     * @param string      $json_string JSON del canvas Fabric.js.
     * @param array       $vars_data   Datos de variables dinámicas.
     * @param string|null $qr_path     Ruta al QR.
     * @return string HTML para mPDF.
     */
    private static function canvas_json_to_html( string $json_string, array $vars_data, ?string $qr_path ): string {
        $canvas = json_decode( $json_string, true );
        if ( ! $canvas || json_last_error() !== JSON_ERROR_NONE ) {
            return '<html><body><p>Error en el diseño de la plantilla.</p></body></html>';
        }

        $canvas_w = (float) ( $canvas['canvasW'] ?? $canvas['width'] ?? 1122 );
        $canvas_h = (float) ( $canvas['canvasH'] ?? $canvas['height'] ?? 794 );

        $objects  = $canvas['objects'] ?? [];
        $bg_color = $canvas['background'] ?? $canvas['backgroundColor'] ?? '#ffffff';
        if ( ! is_string( $bg_color ) ) {
            $bg_color = '#ffffff';
        }

        $bg_image = '';
        if ( ! empty( $canvas['backgroundImage']['src'] ) ) {
            $bg_image = self::resolve_image_src( $canvas['backgroundImage']['src'] );
        }

        $html = sprintf(
            '<html><body style="margin:0;padding:0;"><div style="position:relative;width:%spx;height:%spx;overflow:hidden;background-color:%s;">',
            $canvas_w,
            $canvas_h,
            esc_attr( $bg_color )
        );

        if ( $bg_image ) {
            $html .= sprintf(
                '<img src="%s" style="position:absolute;top:0;left:0;width:%spx;height:%spx;">',
                esc_attr( $bg_image ),
                $canvas_w,
                $canvas_h
            );
        }

        foreach ( $objects as $obj ) {
            $html .= self::render_fabric_object_html( $obj, $vars_data, $qr_path );
        }

        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Renderiza un objeto Fabric.js individual (o grupo) a HTML/SVG compatible con mPDF.
     */
    private static function render_fabric_object_html( array $obj, array $vars_data, ?string $qr_path, float $offset_x = 0.0, float $offset_y = 0.0 ): string {
        if ( isset( $obj['visible'] ) && $obj['visible'] === false ) {
            return '';
        }
        $type   = strtolower( (string) ( $obj['type'] ?? '' ) );
        $name   = (string) ( $obj['name'] ?? '' );
        $scaleX = (float) ( $obj['scaleX'] ?? 1 );
        $scaleY = (float) ( $obj['scaleY'] ?? 1 );
        $w      = (float) ( $obj['width']  ?? 100 ) * $scaleX;
        $h      = (float) ( $obj['height'] ?? 100 ) * $scaleY;

        if ( $type === 'circle' && empty( $obj['width'] ) && ! empty( $obj['radius'] ) ) {
            $w = (float) $obj['radius'] * 2 * $scaleX;
            $h = (float) $obj['radius'] * 2 * $scaleY;
        }

        $left = (float) ( $obj['left'] ?? 0 ) + $offset_x;
        $top  = (float) ( $obj['top']  ?? 0 ) + $offset_y;

        if ( ( $obj['originX'] ?? 'left' ) === 'center' ) {
            $left -= $w / 2;
        } elseif ( ( $obj['originX'] ?? 'left' ) === 'right' ) {
            $left -= $w;
        }
        if ( ( $obj['originY'] ?? 'top' ) === 'center' ) {
            $top -= $h / 2;
        } elseif ( ( $obj['originY'] ?? 'top' ) === 'bottom' ) {
            $top -= $h;
        }

        $angle   = (float) ( $obj['angle'] ?? 0 );
        $opacity = (float) ( $obj['opacity'] ?? 1 );
        $rot_css = $angle !== 0.0 ? sprintf( 'transform:rotate(%sdeg);', $angle ) : '';
        $style   = sprintf( 'position:absolute;top:%spx;left:%spx;opacity:%s;%s', $top, $left, $opacity, $rot_css );

        // ── QR Placeholder (soporta rect, image o group) ──
        if ( $name === 'qr_placeholder' || ( $obj['auraElementType'] ?? '' ) === 'qr' ) {
            if ( $qr_path && file_exists( $qr_path ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                $qr_b64 = 'data:image/png;base64,' . base64_encode( file_get_contents( $qr_path ) );
                return sprintf(
                    '<img src="%s" style="%swidth:%spx;height:%spx;">',
                    esc_attr( $qr_b64 ),
                    $style,
                    max( 40, $w ),
                    max( 40, $h )
                );
            }
            return '';
        }

        // ── Foto de Perfil del Estudiante / Destinatario (Cuadrada, Redondeada o Circular con Marcos y Sombras) ──
        if ( $name === 'student_photo_placeholder' || ( $obj['auraElementType'] ?? '' ) === 'student_photo' ) {
            $raw_photo = (string) ( $vars_data['student_photo_url'] ?? '' );
            if ( $raw_photo === '' && ! empty( $obj['auraCustomPhotoSrc'] ) ) {
                $raw_photo = (string) $obj['auraCustomPhotoSrc'];
            }
            $photo_src = $raw_photo !== '' ? self::resolve_image_src( $raw_photo ) : '';
            if ( $photo_src === '' ) {
                return '';
            }
            return self::render_framed_image_html( $photo_src, $obj, $style, max( 32, $w ), max( 32, $h ), $scaleX );
        }

        // ── Slot dinámico de Imagen de Firma (1..4) ──
        $signer_slot = 0;
        if ( ( $obj['auraElementType'] ?? '' ) === 'signer_sig' && ! empty( $obj['signerSlot'] ) ) {
            $signer_slot = (int) $obj['signerSlot'];
        } elseif ( preg_match( '/^signer_slot_([1-4])$/', $name, $slot_match ) ) {
            $signer_slot = (int) $slot_match[1];
        }
        if ( $signer_slot >= 1 && $signer_slot <= 4 ) {
            $signers = $vars_data['signers'] ?? [];
            $sig_url = is_array( $signers ) && isset( $signers[ $signer_slot - 1 ]['signature_url'] )
                ? (string) $signers[ $signer_slot - 1 ]['signature_url']
                : '';
            if ( $sig_url !== '' ) {
                $src = self::resolve_image_src( $sig_url );
                if ( $src ) {
                    return sprintf(
                        '<img src="%s" style="%swidth:%spx;height:%spx;object-fit:contain;">',
                        esc_attr( $src ),
                        $style,
                        max( 40, $w ),
                        max( 20, $h )
                    );
                }
            }
            return '';
        }

        // ── Cliparts e Imágenes SVG Vectoriales (con colores editados en tiempo real) ──
        if ( ! empty( $obj['auraSvgMarkup'] ) && is_string( $obj['auraSvgMarkup'] ) ) {
            $svg_clean = Aura_Certificates_Admin::sanitize_svg_markup( $obj['auraSvgMarkup'] );
            if ( $svg_clean !== '' ) {
                $svg_w = max( 8, round( $w, 2 ) );
                $svg_h = max( 8, round( $h, 2 ) );
                $svg_sized = preg_replace_callback(
                    '/^<svg\b([^>]*)>/i',
                    static function ( array $m ) use ( $svg_w, $svg_h ): string {
                        $attrs = preg_replace( '/\s+(?:width|height)\s*=\s*(?:"[^"]*"|\'[^\']*\')/i', '', $m[1] );
                        return sprintf( '<svg%s width="%s" height="%s" preserveAspectRatio="xMidYMid meet">', $attrs, $svg_w, $svg_h );
                    },
                    $svg_clean,
                    1
                );
                return sprintf(
                    '<div style="%swidth:%spx;height:%spx;">%s</div>',
                    $style,
                    $svg_w,
                    $svg_h,
                    $svg_sized
                );
            }
        }

        // ── Grupos recursivos (ej. Sellos, Bloques de Firma, Divisores) ──
        if ( $type === 'group' && ! empty( $obj['objects'] ) && is_array( $obj['objects'] ) ) {
            $group_html = '';
            $center_x   = $left + ( $w / 2 );
            $center_y   = $top + ( $h / 2 );

            foreach ( $obj['objects'] as $child ) {
                if ( ! is_array( $child ) ) continue;
                $c_copy           = $child;
                $c_copy['scaleX'] = (float) ( $child['scaleX'] ?? 1 ) * $scaleX;
                $c_copy['scaleY'] = (float) ( $child['scaleY'] ?? 1 ) * $scaleY;
                if ( isset( $child['left'] ) ) {
                    $c_copy['left'] = (float) $child['left'] * $scaleX;
                }
                if ( isset( $child['top'] ) ) {
                    $c_copy['top'] = (float) $child['top'] * $scaleY;
                }
                $group_html .= self::render_fabric_object_html( $c_copy, $vars_data, $qr_path, $center_x, $center_y );
            }
            return $group_html;
        }

        // ── Textos ──
        if ( in_array( $type, [ 'textbox', 'text', 'i-text' ], true ) ) {
            $text      = Aura_Certificates_Templates::replace_vars( (string) ( $obj['text'] ?? '' ), $vars_data );
            $font_size = (float) ( $obj['fontSize'] ?? 16 ) * $scaleY;
            $font_fam  = self::map_mpdf_font( (string) ( $obj['fontFamily'] ?? 'sans-serif' ) );
            $color     = esc_attr( $obj['fill'] ?? '#000000' );
            $align     = esc_attr( $obj['textAlign'] ?? 'left' );
            $bold      = ( $obj['fontWeight'] ?? '' ) === 'bold' ? 'bold' : 'normal';
            $italic    = ( $obj['fontStyle'] ?? '' ) === 'italic' ? 'italic' : 'normal';
            $deco      = [];
            if ( ! empty( $obj['underline'] ) ) {
                $deco[] = 'underline';
            }
            if ( ! empty( $obj['linethrough'] ) ) {
                $deco[] = 'line-through';
            }
            $deco_css    = ! empty( $deco ) ? 'text-decoration:' . implode( ' ', $deco ) . ';' : '';
            $line_height = ! empty( $obj['lineHeight'] ) ? 'line-height:' . (float) $obj['lineHeight'] . ';' : '';
            $char_space  = ! empty( $obj['charSpacing'] ) ? 'letter-spacing:' . round( (float) $obj['charSpacing'] * $font_size / 1000, 2 ) . 'px;' : '';
            $bg_text     = ! empty( $obj['textBackgroundColor'] ) ? 'background-color:' . esc_attr( $obj['textBackgroundColor'] ) . ';' : '';

            return sprintf(
                '<div style="%swidth:%spx;font-size:%spx;font-family:%s;color:%s;text-align:%s;font-weight:%s;font-style:%s;%s%s%s%swhite-space:pre-wrap;">%s</div>',
                $style, max( 20, $w ), $font_size, $font_fam, $color, $align, $bold, $italic,
                $deco_css, $line_height, $char_space, $bg_text,
                nl2br( esc_html( $text ) )
            );
        }

        // ── Imágenes ──
        if ( $type === 'image' ) {
            $src_raw = (string) ( $obj['src'] ?? '' );
            $is_qr   = str_contains( strtolower( $src_raw ), 'qr' );
            if ( $is_qr && $qr_path && file_exists( $qr_path ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                $src = 'data:image/png;base64,' . base64_encode( file_get_contents( $qr_path ) );
            } else {
                $src = self::resolve_image_src( $src_raw );
            }

            if ( $src ) {
                if (
                    ! empty( $obj['auraPhotoShape'] )
                    || (float) ( $obj['strokeWidth'] ?? 0 ) > 0
                    || (float) ( $obj['auraPhotoPad'] ?? 0 ) > 0
                    || ! empty( $obj['shadow'] )
                ) {
                    return self::render_framed_image_html( $src, $obj, $style, $w, $h, $scaleX );
                }
                return sprintf(
                    '<img src="%s" style="%swidth:%spx;height:%spx;">',
                    esc_attr( $src ), $style, $w, $h
                );
            }
            return '';
        }

        // ── Líneas ──
        if ( $type === 'line' ) {
            $stroke   = esc_attr( $obj['stroke'] ?? '#333333' );
            $sw       = max( 1, (float) ( $obj['strokeWidth'] ?? 2 ) );
            $dash     = ! empty( $obj['strokeDashArray'] ) && is_array( $obj['strokeDashArray'] )
                            ? 'dashed'
                            : 'solid';
            $svg_w    = max( 2, $w );
            $svg_h    = max( $sw + 2, $h );
            $x1       = (float) ( $obj['x1'] ?? -$w / 2 ) * $scaleX + ( $svg_w / 2 );
            $y1       = (float) ( $obj['y1'] ?? 0 ) * $scaleY + ( $svg_h / 2 );
            $x2       = (float) ( $obj['x2'] ?? $w / 2 ) * $scaleX + ( $svg_w / 2 );
            $y2       = (float) ( $obj['y2'] ?? 0 ) * $scaleY + ( $svg_h / 2 );
            $dash_att = $dash === 'dashed' ? ' stroke-dasharray="' . esc_attr( implode( ',', array_map( 'floatval', $obj['strokeDashArray'] ) ) ) . '"' : '';

            return sprintf(
                '<div style="%swidth:%spx;height:%spx;"><svg width="%s" height="%s" viewBox="0 0 %s %s" xmlns="http://www.w3.org/2000/svg"><line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="%s"%s /></svg></div>',
                $style, $svg_w, $svg_h, $svg_w, $svg_h, $svg_w, $svg_h, $x1, $y1, $x2, $y2, $stroke, $sw, $dash_att
            );
        }

        // ── Triángulos y Polígonos (estrellas, cintas, sellos) ──
        if ( in_array( $type, [ 'triangle', 'polygon', 'polyline' ], true ) ) {
            $fill   = ! empty( $obj['fill'] ) ? esc_attr( $obj['fill'] ) : 'none';
            $stroke = ! empty( $obj['stroke'] ) ? esc_attr( $obj['stroke'] ) : 'none';
            $sw     = (float) ( $obj['strokeWidth'] ?? 0 );
            $svg_w  = max( 4, $w );
            $svg_h  = max( 4, $h );

            if ( $type === 'triangle' ) {
                $pts = sprintf( '%s,0 %s,%s 0,%s', $svg_w / 2, $svg_w, $svg_h, $svg_h );
            } else {
                $raw_pts = $obj['points'] ?? [];
                $pairs   = [];
                $xs      = array_column( $raw_pts, 'x' );
                $ys      = array_column( $raw_pts, 'y' );
                $min_x   = ! empty( $xs ) ? min( $xs ) : 0;
                $min_y   = ! empty( $ys ) ? min( $ys ) : 0;
                foreach ( $raw_pts as $pt ) {
                    $px      = ( ( (float) ( $pt['x'] ?? 0 ) ) - $min_x ) * $scaleX;
                    $py      = ( ( (float) ( $pt['y'] ?? 0 ) ) - $min_y ) * $scaleY;
                    $pairs[] = round( $px, 2 ) . ',' . round( $py, 2 );
                }
                $pts = implode( ' ', $pairs );
            }

            if ( $pts !== '' ) {
                $tag = $type === 'polyline' ? 'polyline' : 'polygon';
                return sprintf(
                    '<div style="%swidth:%spx;height:%spx;"><svg width="%s" height="%s" viewBox="0 0 %s %s" xmlns="http://www.w3.org/2000/svg"><%s points="%s" fill="%s" stroke="%s" stroke-width="%s" /></svg></div>',
                    $style, $svg_w, $svg_h, $svg_w, $svg_h, $svg_w, $svg_h, $tag, esc_attr( $pts ), $fill, $stroke, $sw
                );
            }
            return '';
        }

        // ── Paths SVG individuales ──
        if ( $type === 'path' && ! empty( $obj['path'] ) ) {
            $d_str = '';
            if ( is_string( $obj['path'] ) ) {
                $d_str = $obj['path'];
            } elseif ( is_array( $obj['path'] ) ) {
                $chunks = [];
                foreach ( $obj['path'] as $cmd ) {
                    if ( is_array( $cmd ) && ! empty( $cmd ) ) {
                        $chunks[] = implode( ' ', array_map( 'strval', $cmd ) );
                    }
                }
                $d_str = implode( ' ', $chunks );
            }
            if ( $d_str !== '' ) {
                $fill   = ! empty( $obj['fill'] ) ? esc_attr( $obj['fill'] ) : 'none';
                $stroke = ! empty( $obj['stroke'] ) ? esc_attr( $obj['stroke'] ) : 'none';
                $sw     = (float) ( $obj['strokeWidth'] ?? 0 );
                $vb_w   = max( 4, (float) ( $obj['width'] ?? $w ) );
                $vb_h   = max( 4, (float) ( $obj['height'] ?? $h ) );
                $po_x   = (float) ( $obj['pathOffset']['x'] ?? ( $vb_w / 2 ) );
                $po_y   = (float) ( $obj['pathOffset']['y'] ?? ( $vb_h / 2 ) );
                $tx     = round( ( $vb_w / 2 ) - $po_x, 2 );
                $ty     = round( ( $vb_h / 2 ) - $po_y, 2 );
                return sprintf(
                    '<div style="%swidth:%spx;height:%spx;"><svg width="%s" height="%s" viewBox="0 0 %s %s" xmlns="http://www.w3.org/2000/svg"><g transform="translate(%s,%s)"><path d="%s" fill="%s" stroke="%s" stroke-width="%s" /></g></svg></div>',
                    $style, max( 4, $w ), max( 4, $h ), max( 4, $w ), max( 4, $h ), $vb_w, $vb_h, $tx, $ty, esc_attr( $d_str ), $fill, $stroke, $sw
                );
            }
            return '';
        }

        // ── Rectángulos, Círculos y Elipses ──
        if ( in_array( $type, [ 'rect', 'circle', 'ellipse' ], true ) ) {
            $fill         = ! empty( $obj['fill'] ) ? esc_attr( $obj['fill'] ) : 'transparent';
            $stroke       = ! empty( $obj['stroke'] ) ? esc_attr( $obj['stroke'] ) : 'transparent';
            $sw           = (float) ( $obj['strokeWidth'] ?? 0 );
            $border_style = ! empty( $obj['strokeDashArray'] ) ? 'dashed' : 'solid';
            $rx           = (float) ( $obj['rx'] ?? 0 ) * $scaleX;
            $radius       = in_array( $type, [ 'circle', 'ellipse' ], true )
                            ? '50%'
                            : ( $rx > 0 ? $rx . 'px' : '0' );

            return sprintf(
                '<div style="%swidth:%spx;height:%spx;background-color:%s;border:%spx %s %s;border-radius:%s;box-sizing:border-box;"></div>',
                $style, max( 1, $w ), max( 1, $h ), $fill, $sw, $border_style, $stroke, $radius
            );
        }

        return '';
    }

    /**
     * Mapea familias tipográficas del editor a fuentes compatibles en mPDF.
     */
    private static function map_mpdf_font( string $family ): string {
        $f = strtolower( trim( $family ) );
        if ( str_contains( $f, 'times' ) || str_contains( $f, 'georgia' ) || str_contains( $f, 'playfair' ) || str_contains( $f, 'cinzel' ) || str_contains( $f, 'merriweather' ) || str_contains( $f, 'vibes' ) || str_contains( $f, 'dancing' ) ) {
            return 'DejaVuSerif, Georgia, serif';
        }
        if ( str_contains( $f, 'courier' ) || str_contains( $f, 'mono' ) ) {
            return 'DejaVuSansMono, monospace';
        }
        return 'DejaVuSans, Arial, sans-serif';
    }

    /**
     * Resuelve una URL/ruta de imagen a base64 embebido para el PDF.
     * Soporta data URLs (ya base64) y URLs del sitio (las convierte a path local).
     *
     * @param string $src
     * @return string Data URL base64 o cadena vacía.
     */
    private static function resolve_image_src( string $src ): string {
        if ( empty( $src ) ) {
            return '';
        }

        // Ya es data URL
        if ( str_starts_with( $src, 'data:image/' ) ) {
            return $src;
        }

        // Convertir URL del sitio a path local
        $upload_dir = wp_upload_dir();
        $base_url   = $upload_dir['baseurl'];
        $base_dir   = $upload_dir['basedir'];

        $site_url = site_url();

        if ( str_starts_with( $src, $base_url ) ) {
            $local_path = str_replace( $base_url, $base_dir, $src );
        } elseif ( str_starts_with( $src, $site_url ) ) {
            $local_path = str_replace( $site_url, ABSPATH, $src );
        } else {
            // URL externa: no permitida por seguridad
            return '';
        }

        $local_path = realpath( $local_path );

        if ( ! $local_path || ! file_exists( $local_path ) ) {
            return '';
        }

        // Solo imágenes permitidas (incluye SVG)
        $ext_file     = strtolower( pathinfo( $local_path, PATHINFO_EXTENSION ) );
        $allowed_mime = [ 'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml' ];
        $finfo        = new \finfo( FILEINFO_MIME_TYPE );
        $mime         = $finfo->file( $local_path );

        // Archivos .svg sin cabecera <?xml suelen detectarse como text/plain o text/xml
        if ( $ext_file === 'svg' || $mime === 'image/svg+xml' || $mime === 'image/svg' ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $clean_svg = Aura_Certificates_Admin::sanitize_svg_markup( (string) file_get_contents( $local_path ) );
            if ( $clean_svg === '' ) {
                return '';
            }
            return 'data:image/svg+xml;base64,' . base64_encode( $clean_svg );
        }

        if ( ! in_array( $mime, $allowed_mime, true ) ) {
            return '';
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        return 'data:' . $mime . ';base64,' . base64_encode( file_get_contents( $local_path ) );
    }

    /**
     * Renderiza una foto de perfil o imagen con forma (cuadrada, redondeada, circular),
     * marco/borde (sólido, punteado, doble), relleno interior y sombra usando SVG para compatibilidad total con mPDF.
     */
    private static function render_framed_image_html( string $src, array $obj, string $base_style, float $w, float $h, float $scaleX = 1.0 ): string {
        $shape        = strtolower( (string) ( $obj['auraPhotoShape'] ?? 'circle' ) );
        $stroke       = ! empty( $obj['stroke'] ) && $obj['stroke'] !== 'transparent' ? (string) $obj['stroke'] : '#c9a84c';
        $sw           = max( 0.0, (float) ( $obj['strokeWidth'] ?? 0 ) );
        $fill         = ! empty( $obj['fill'] ) && $obj['fill'] !== 'transparent' ? (string) $obj['fill'] : '#ffffff';
        $pad          = max( 0.0, (float) ( $obj['auraPhotoPad'] ?? 0 ) );
        $border_style = strtolower( (string) ( $obj['auraPhotoBorderStyle'] ?? 'solid' ) );
        if ( ! empty( $obj['strokeDashArray'] ) && is_array( $obj['strokeDashArray'] ) && $border_style === 'solid' ) {
            $border_style = 'dashed';
        }

        $radius = 0.0;
        if ( $shape === 'rounded' ) {
            $raw_rx = isset( $obj['auraPhotoRadius'] ) ? (float) $obj['auraPhotoRadius'] : (float) ( $obj['rx'] ?? 18 );
            $radius = max( 4.0, min( min( $w, $h ) / 2, $raw_rx * $scaleX ) );
        }

        $has_shadow = ! empty( $obj['shadow'] );
        $sh_dx      = 0.0;
        $sh_dy      = 0.0;
        if ( is_array( $obj['shadow'] ?? null ) ) {
            $sh_dx = max( 0.0, (float) ( $obj['shadow']['offsetX'] ?? 4 ) );
            $sh_dy = max( 0.0, (float) ( $obj['shadow']['offsetY'] ?? 5 ) );
        } elseif ( $has_shadow ) {
            $sh_dx = 4.0;
            $sh_dy = 5.0;
        }

        $svg_w   = round( $w + $sh_dx + 4, 2 );
        $svg_h   = round( $h + $sh_dy + 4, 2 );
        $clip_id = 'aura_clip_' . substr( md5( uniqid( '', true ) ), 0, 8 );

        $inset   = max( 1.0, $sw / 2 ) + $pad;
        $img_x   = round( $inset, 2 );
        $img_y   = round( $inset, 2 );
        $img_w   = max( 10.0, round( $w - ( $inset * 2 ), 2 ) );
        $img_h   = max( 10.0, round( $h - ( $inset * 2 ), 2 ) );
        $dash    = $border_style === 'dashed' ? ' stroke-dasharray="6,4"' : '';

        $defs_html   = '';
        $shadow_html = '';
        $frame_bg    = '';
        $frame_ring  = '';

        if ( $shape === 'circle' ) {
            $cx = round( $w / 2, 2 );
            $cy = round( $h / 2, 2 );
            $r_outer = max( 8.0, round( min( $w, $h ) / 2 - max( 1.0, $sw / 2 ), 2 ) );
            $r_clip  = max( 6.0, round( min( $img_w, $img_h ) / 2, 2 ) );

            $defs_html = sprintf(
                '<defs><clipPath id="%s"><circle cx="%s" cy="%s" r="%s" /></clipPath></defs>',
                esc_attr( $clip_id ), $cx, $cy, $r_clip
            );
            if ( $has_shadow ) {
                $shadow_html = sprintf(
                    '<circle cx="%s" cy="%s" r="%s" fill="#0f172a" fill-opacity="0.16" />',
                    round( $cx + $sh_dx, 2 ), round( $cy + $sh_dy, 2 ), $r_outer
                );
            }
            $frame_bg = sprintf(
                '<circle cx="%s" cy="%s" r="%s" fill="%s" />',
                $cx, $cy, $r_outer, esc_attr( $fill )
            );
            if ( $sw > 0 ) {
                $frame_ring = sprintf(
                    '<circle cx="%s" cy="%s" r="%s" fill="none" stroke="%s" stroke-width="%s"%s />',
                    $cx, $cy, $r_outer, esc_attr( $stroke ), round( $sw, 2 ), $dash
                );
                if ( $border_style === 'double' ) {
                    $r_inner = max( 4.0, round( $r_outer - max( 3.0, $sw * 1.15 ), 2 ) );
                    $frame_ring .= sprintf(
                        '<circle cx="%s" cy="%s" r="%s" fill="none" stroke="%s" stroke-width="%s" stroke-opacity="0.7" />',
                        $cx, $cy, $r_inner, esc_attr( $stroke ), max( 1.0, round( $sw * 0.45, 2 ) )
                    );
                }
            }
        } else {
            $rx_outer = $shape === 'rounded' ? round( $radius, 2 ) : 0;
            $rx_clip  = $shape === 'rounded' ? max( 2.0, round( $radius - $pad, 2 ) ) : 0;
            $b_inset  = round( max( 0.5, $sw / 2 ), 2 );
            $b_w      = max( 10.0, round( $w - $sw, 2 ) );
            $b_h      = max( 10.0, round( $h - $sw, 2 ) );

            $defs_html = sprintf(
                '<defs><clipPath id="%s"><rect x="%s" y="%s" width="%s" height="%s" rx="%s" ry="%s" /></clipPath></defs>',
                esc_attr( $clip_id ), $img_x, $img_y, $img_w, $img_h, $rx_clip, $rx_clip
            );
            if ( $has_shadow ) {
                $shadow_html = sprintf(
                    '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" ry="%s" fill="#0f172a" fill-opacity="0.16" />',
                    round( $b_inset + $sh_dx, 2 ), round( $b_inset + $sh_dy, 2 ), $b_w, $b_h, $rx_outer, $rx_outer
                );
            }
            $frame_bg = sprintf(
                '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" ry="%s" fill="%s" />',
                $b_inset, $b_inset, $b_w, $b_h, $rx_outer, $rx_outer, esc_attr( $fill )
            );
            if ( $sw > 0 ) {
                $frame_ring = sprintf(
                    '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" ry="%s" fill="none" stroke="%s" stroke-width="%s"%s />',
                    $b_inset, $b_inset, $b_w, $b_h, $rx_outer, $rx_outer, esc_attr( $stroke ), round( $sw, 2 ), $dash
                );
                if ( $border_style === 'double' ) {
                    $d_off = max( 3.0, $sw * 1.15 );
                    $frame_ring .= sprintf(
                        '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" ry="%s" fill="none" stroke="%s" stroke-width="%s" stroke-opacity="0.7" />',
                        round( $b_inset + $d_off, 2 ),
                        round( $b_inset + $d_off, 2 ),
                        max( 6.0, round( $b_w - $d_off * 2, 2 ) ),
                        max( 6.0, round( $b_h - $d_off * 2, 2 ) ),
                        max( 0.0, round( $rx_outer - $d_off, 2 ) ),
                        max( 0.0, round( $rx_outer - $d_off, 2 ) ),
                        esc_attr( $stroke ),
                        max( 1.0, round( $sw * 0.45, 2 ) )
                    );
                }
            }
        }

        $img_tag = sprintf(
            '<image href="%s" xlink:href="%s" x="%s" y="%s" width="%s" height="%s" preserveAspectRatio="xMidYMid slice" clip-path="url(#%s)" />',
            esc_attr( $src ), esc_attr( $src ), $img_x, $img_y, $img_w, $img_h, esc_attr( $clip_id )
        );

        return sprintf(
            '<div style="%swidth:%spx;height:%spx;"><svg width="%s" height="%s" viewBox="0 0 %s %s" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">%s%s%s%s%s</svg></div>',
            $base_style, $svg_w, $svg_h, $svg_w, $svg_h, $svg_w, $svg_h,
            $defs_html, $shadow_html, $frame_bg, $img_tag, $frame_ring
        );
    }

    /**
     * Obtiene la URL de la foto de perfil del destinatario (estudiante o usuario WP).
     */
    private static function resolve_recipient_photo_url( int $student_id = 0, int $wp_user_id = 0, string $explicit_url = '' ): string {
        if ( $explicit_url !== '' ) {
            return $explicit_url;
        }

        global $wpdb;
        $students_table = $wpdb->prefix . 'aura_students';

        if ( $student_id > 0 ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $s_row = $wpdb->get_row( $wpdb->prepare( "SELECT photo_url, wp_user_id FROM {$students_table} WHERE id = %d", $student_id ) );
            if ( $s_row ) {
                if ( ! empty( $s_row->photo_url ) ) {
                    return (string) $s_row->photo_url;
                }
                if ( $wp_user_id === 0 && ! empty( $s_row->wp_user_id ) ) {
                    $wp_user_id = (int) $s_row->wp_user_id;
                }
            }
        }

        if ( $wp_user_id > 0 ) {
            // 1. Buscar en aura_students por wp_user_id
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $photo_from_student = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT photo_url FROM {$students_table} WHERE wp_user_id = %d AND photo_url IS NOT NULL AND photo_url != '' LIMIT 1",
                    $wp_user_id
                )
            );
            if ( ! empty( $photo_from_student ) ) {
                return (string) $photo_from_student;
            }

            // 2. Buscar en user_meta comunes de foto de perfil
            $meta_keys = [ 'aura_photo_url', 'aura_student_photo', 'profile_photo', 'custom_avatar' ];
            foreach ( $meta_keys as $mk ) {
                $val = get_user_meta( $wp_user_id, $mk, true );
                if ( is_string( $val ) && $val !== '' ) {
                    return $val;
                }
                if ( is_numeric( $val ) && (int) $val > 0 ) {
                    $att_url = wp_get_attachment_image_url( (int) $val, 'medium' );
                    if ( $att_url ) {
                        return $att_url;
                    }
                }
            }
        }

        return '';
    }
}
