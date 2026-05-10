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
        // Revocar
        add_action( 'wp_ajax_aura_cert_revoke',         [ __CLASS__, 'ajax_revoke' ] );
        // Descarga segura del PDF (autenticado)
        add_action( 'wp_ajax_aura_cert_download',       [ __CLASS__, 'ajax_download' ] );
        // Descarga pública (con token en URL)
        add_action( 'wp_ajax_nopriv_aura_cert_download',[ __CLASS__, 'ajax_download' ] );
        // Emisión masiva
        add_action( 'wp_ajax_aura_cert_queue_bulk',     [ __CLASS__, 'ajax_queue_bulk' ] );
        add_action( 'wp_ajax_aura_cert_bulk_status',    [ __CLASS__, 'ajax_bulk_status' ] );
        // Cron: procesa la cola de emisión masiva
        add_action( 'aura_certs_bulk_process',          [ __CLASS__, 'process_bulk_queue' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Emitir certificado individual
    // ─────────────────────────────────────────────────────────────

    public static function ajax_issue(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_issue' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para emitir certificados.', 'aura-suite' ) ], 403 );
        }

        $student_id    = absint( $_POST['student_id']    ?? 0 );
        $enrollment_id = absint( $_POST['enrollment_id'] ?? 0 );
        $template_id   = absint( $_POST['template_id']   ?? 0 );
        $extra          = [
            'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'send_email'  => (bool) ( $_POST['send_email'] ?? Aura_Certificates_Settings::get( 'default_send_email', true ) ),
            'send_wa'     => (bool) ( $_POST['send_whatsapp'] ?? Aura_Certificates_Settings::get( 'default_send_whatsapp', false ) ),
        ];

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
            'message'        => __( 'Certificado emitido correctamente.', 'aura-suite' ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // LÓGICA CENTRAL DE EMISIÓN
    // ─────────────────────────────────────────────────────────────

    /**
     * Emite un certificado: genera folio, QR, resuelve variables, genera PDF y lo guarda.
     *
     * @param int   $student_id
     * @param int   $enrollment_id
     * @param int   $template_id  0 = usar plantilla por defecto
     * @param array $extra        ['description', 'send_email', 'send_wa']
     * @return array|WP_Error ['certificate_id' => int, 'folio' => string, 'pdf_path' => string]
     */
    public static function issue_certificate( int $student_id, int $enrollment_id, int $template_id = 0, array $extra = [] ): array|\WP_Error {
        global $wpdb;

        // ── Obtener datos del estudiante / inscripción ──────────────
        $students_table    = $wpdb->prefix . 'aura_students';
        $enrollments_table = $wpdb->prefix . 'aura_enrollments';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$students_table} WHERE id = %d", $student_id ) );
        if ( ! $student ) {
            return new \WP_Error( 'student_not_found', __( 'Estudiante no encontrado.', 'aura-suite' ) );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $enrollment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$enrollments_table} WHERE id = %d AND student_id = %d", $enrollment_id, $student_id ) );
        if ( ! $enrollment ) {
            return new \WP_Error( 'enrollment_not_found', __( 'Inscripción no encontrada.', 'aura-suite' ) );
        }

        // ── Plantilla de diseño ─────────────────────────────────────
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

        // ── Folio y UUID ────────────────────────────────────────────
        $folio = Aura_Certificates_Folio::generate();
        $uuid  = wp_generate_uuid4();

        // ── URL de verificación ─────────────────────────────────────
        $verify_slug = Aura_Certificates_Settings::get( 'verify_slug', 'verificar-certificado' );
        $verify_url  = trailingslashit( home_url() ) . $verify_slug . '/' . $folio;

        // ── Generar QR ──────────────────────────────────────────────
        $qr_path = self::generate_qr( $folio, $verify_url );

        // ── Variables dinámicas ─────────────────────────────────────
        $org_name = get_option( 'blogname', '' );
        if ( Aura_Certificates_Settings::get( 'org_name', '' ) ) {
            $org_name = Aura_Certificates_Settings::get( 'org_name', $org_name );
        }

        $vars_data = [
            'first_name'       => $student->first_name    ?? '',
            'last_name'        => $student->last_name      ?? '',
            'course_name'      => $enrollment->course_name ?? '',
            'program_name'     => $enrollment->program_name ?? '',
            'issued_at'        => current_time( 'mysql' ),
            'graduation_date'  => $enrollment->graduation_date ?? '',
            'instructor_name'  => $enrollment->instructor_name ?? '',
            'organization_name'=> $org_name,
            'folio'            => $folio,
            'description'      => $extra['description'] ?? '',
        ];

        // ── Generar PDF ─────────────────────────────────────────────
        $pdf_result = self::generate_pdf( $template, $vars_data, $folio, $qr_path );
        if ( is_wp_error( $pdf_result ) ) {
            return $pdf_result;
        }

        // ── Guardar en BD ───────────────────────────────────────────
        $certs_table = $wpdb->prefix . 'aura_certificates';

        $wpdb->insert(
            $certs_table,
            [
                'uuid'          => $uuid,
                'folio'         => $folio,
                'student_id'    => $student_id,
                'enrollment_id' => $enrollment_id,
                'template_id'   => (int) $template->id,
                'course_name'   => $vars_data['course_name'],
                'program_name'  => $vars_data['program_name'],
                'issued_by'     => get_current_user_id(),
                'issued_at'     => current_time( 'mysql' ),
                'pdf_path'      => $pdf_result['pdf_path'],
                'qr_path'       => $qr_path ?? '',
                'verify_url'    => $verify_url,
                'status'        => 'active',
                'description'   => $extra['description'] ?? '',
                'send_email'    => (int) ( $extra['send_email'] ?? 0 ),
                'send_whatsapp' => (int) ( $extra['send_wa']    ?? 0 ),
            ],
            [ '%s','%s','%d','%d','%d','%s','%s','%d','%s','%s','%s','%s','%s','%s','%d','%d' ]
        );

        $cert_id = (int) $wpdb->insert_id;

        if ( ! $cert_id ) {
            return new \WP_Error( 'db_error', __( 'Error al guardar el certificado en la base de datos.', 'aura-suite' ) );
        }

        // ── Asignar capacidad de descarga al usuario WP del estudiante ──
        $wp_user_id = $student->wp_user_id ?? 0;
        if ( $wp_user_id ) {
            $user = get_user_by( 'id', (int) $wp_user_id );
            if ( $user ) {
                $user->add_cap( 'aura_cert_download_own' );
            }
        }

        // ── Limpiar transient de "listo para emitir" ────────────────
        delete_transient( 'aura_cert_ready_' . $student_id );

        // ── Notificaciones ──────────────────────────────────────────
        if ( class_exists( 'Aura_Certificates_Notifications' ) ) {
            if ( $extra['send_email'] ?? false ) {
                Aura_Certificates_Notifications::send_issued_email( $cert_id );
            }
            if ( $extra['send_wa'] ?? false ) {
                Aura_Certificates_Notifications::send_issued_whatsapp( $cert_id );
            }
        }

        do_action( 'aura_certificate_issued', $cert_id, $student_id, $enrollment_id );

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
        $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ], 400 );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $cert = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
        if ( ! $cert ) {
            wp_send_json_error( [ 'message' => __( 'Certificado no encontrado.', 'aura-suite' ) ], 404 );
        }

        $wpdb->update(
            $table,
            [
                'status'            => 'revoked',
                'revoke_reason'     => $reason,
                'revoked_at'        => current_time( 'mysql' ),
                'revoked_by'        => get_current_user_id(),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%s', '%d' ],
            [ '%d' ]
        );

        if ( class_exists( 'Aura_Certificates_Notifications' ) ) {
            Aura_Certificates_Notifications::send_revoke_email( $id );
        }

        do_action( 'aura_certificate_revoked', $id, $reason );

        wp_send_json_success( [ 'message' => __( 'Certificado revocado.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Descarga segura de PDF
    // ─────────────────────────────────────────────────────────────

    public static function ajax_download(): void {
        $folio = sanitize_text_field( wp_unslash( $_REQUEST['folio'] ?? '' ) );

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

        // Verificar propiedad: administrador O es el propio estudiante
        if ( ! current_user_can( 'aura_cert_download_any' ) && ! current_user_can( 'manage_options' ) ) {
            // Verificar por token firmado o por usuario autenticado
            $current_user = wp_get_current_user();
            if ( ! $current_user->ID ) {
                wp_die( esc_html__( 'Acceso denegado.', 'aura-suite' ), 403 );
            }

            $students_table = $wpdb->prefix . 'aura_students';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $student = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$students_table} WHERE wp_user_id = %d", $current_user->ID ) );
            if ( ! $student || (int) $student->id !== (int) $cert->student_id ) {
                wp_die( esc_html__( 'No tiene permiso para descargar este certificado.', 'aura-suite' ), 403 );
            }
        }

        $pdf_path = $cert->pdf_path;

        if ( ! file_exists( $pdf_path ) ) {
            // Intentar regenerar
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
     * Encola certificados para emisión masiva.
     * Guarda la lista en un transient y programa el cron.
     */
    public static function ajax_queue_bulk(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_issue' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        $items_raw   = $_POST['items'] ?? [];
        $template_id = absint( $_POST['template_id'] ?? 0 );

        if ( ! is_array( $items_raw ) || empty( $items_raw ) ) {
            wp_send_json_error( [ 'message' => __( 'No hay estudiantes seleccionados.', 'aura-suite' ) ], 400 );
        }

        $items = array_map( static function ( $item ) {
            return [
                'student_id'    => absint( $item['student_id']    ?? 0 ),
                'enrollment_id' => absint( $item['enrollment_id'] ?? 0 ),
            ];
        }, $items_raw );

        $items = array_filter( $items, static fn( $i ) => $i['student_id'] > 0 && $i['enrollment_id'] > 0 );

        if ( empty( $items ) ) {
            wp_send_json_error( [ 'message' => __( 'Los datos de estudiantes no son válidos.', 'aura-suite' ) ], 400 );
        }

        $batch_id = wp_generate_uuid4();

        $queue = [
            'batch_id'    => $batch_id,
            'template_id' => $template_id,
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

        // Programar el cron si no está ya programado
        if ( ! wp_next_scheduled( 'aura_certs_bulk_process' ) ) {
            wp_schedule_single_event( time() + 5, 'aura_certs_bulk_process' );
        }

        wp_send_json_success( [
            'batch_id' => $batch_id,
            'total'    => count( $items ),
            'message'  => __( 'Procesamiento masivo encolado.', 'aura-suite' ),
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
            'success'    => $queue['success'],
            'failed'     => $queue['failed'],
            'percent'    => $percent,
        ] );
    }

    /**
     * Procesador de la cola masiva (llamado por el cron).
     * Procesa hasta 10 ítems por ejecución.
     */
    public static function process_bulk_queue(): void {
        global $wpdb;

        // Buscar todos los transientes de batch activos (paginamos por alg. encola a la vez)
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
            $queue    = get_transient( 'aura_cert_bulk_' . $batch_id );

            if ( ! $queue || $queue['status'] === 'completed' ) {
                continue;
            }

            $queue['status'] = 'processing';

            $pending = array_slice( $queue['items'], $queue['processed'], 10 );

            foreach ( $pending as $item ) {
                $result = self::issue_certificate(
                    $item['student_id'],
                    $item['enrollment_id'],
                    $queue['template_id']
                );

                $queue['processed']++;
                if ( is_wp_error( $result ) ) {
                    $queue['failed']++;
                } else {
                    $queue['success']++;
                }
            }

            if ( $queue['processed'] >= $queue['total'] ) {
                $queue['status'] = 'completed';

                // Notificar al administrador que emitió el batch
                if ( class_exists( 'Aura_Certificates_Notifications' ) ) {
                    Aura_Certificates_Notifications::send_bulk_complete_email(
                        $queue['created_by'],
                        [
                            'total'   => $queue['total'],
                            'success' => $queue['success'],
                            'failed'  => $queue['failed'],
                        ]
                    );
                }
            } else {
                // Reprogramar cron para el siguiente bloque
                if ( ! wp_next_scheduled( 'aura_certs_bulk_process' ) ) {
                    wp_schedule_single_event( time() + 120, 'aura_certs_bulk_process' );
                }
            }

            set_transient( 'aura_cert_bulk_' . $batch_id, $queue, DAY_IN_SECONDS );
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

        $canvas_w = (float) ( $canvas['width']  ?? 1122 ); // px a 96dpi A4-L
        $canvas_h = (float) ( $canvas['height'] ?? 794  );

        $objects = $canvas['objects'] ?? [];

        // Fondo del canvas
        $bg_color = $canvas['background'] ?? '#ffffff';
        $bg_image = '';
        if ( ! empty( $canvas['backgroundImage'] ) ) {
            $bg_image = self::resolve_image_src( $canvas['backgroundImage']['src'] ?? '' );
        }

        $html = sprintf(
            '<html><body style="margin:0;padding:0;">
            <div style="position:relative;width:%spx;height:%spx;overflow:hidden;background-color:%s;">',
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
            $type = $obj['type'] ?? '';
            $top  = (float) ( $obj['top']  ?? 0 );
            $left = (float) ( $obj['left'] ?? 0 );

            // Calcular posición absoluta considerando originX/originY
            if ( ( $obj['originX'] ?? 'left' ) === 'center' ) {
                $left -= (float) ( $obj['width'] ?? 0 ) * (float) ( $obj['scaleX'] ?? 1 ) / 2;
            }
            if ( ( $obj['originY'] ?? 'top' ) === 'center' ) {
                $top  -= (float) ( $obj['height'] ?? 0 ) * (float) ( $obj['scaleY'] ?? 1 ) / 2;
            }

            $angle    = (float) ( $obj['angle'] ?? 0 );
            $opacity  = (float) ( $obj['opacity'] ?? 1 );
            $style    = sprintf(
                'position:absolute;top:%spx;left:%spx;opacity:%s;transform:rotate(%sdeg);',
                $top, $left, $opacity, $angle
            );

            if ( in_array( $type, [ 'textbox', 'text', 'i-text' ], true ) ) {
                $text      = Aura_Certificates_Templates::replace_vars( $obj['text'] ?? '', $vars_data );
                $font_size = (float) ( $obj['fontSize'] ?? 16 ) * (float) ( $obj['scaleY'] ?? 1 );
                $font_fam  = esc_attr( $obj['fontFamily'] ?? 'sans-serif' );
                $color     = esc_attr( $obj['fill'] ?? '#000000' );
                $align     = esc_attr( $obj['textAlign'] ?? 'left' );
                $bold      = ( $obj['fontWeight'] ?? '' ) === 'bold' ? 'bold' : 'normal';
                $italic    = ( $obj['fontStyle'] ?? '' ) === 'italic' ? 'italic' : 'normal';
                $width_px  = (float) ( $obj['width'] ?? 300 ) * (float) ( $obj['scaleX'] ?? 1 );

                $html .= sprintf(
                    '<div style="%swidth:%spx;font-size:%spx;font-family:\'%s\',sans-serif;color:%s;text-align:%s;font-weight:%s;font-style:%s;white-space:pre-wrap;">%s</div>',
                    $style, $width_px, $font_size, $font_fam, $color, $align, $bold, $italic,
                    nl2br( esc_html( $text ) )
                );

            } elseif ( $type === 'image' ) {
                $src_raw = $obj['src'] ?? '';

                // Detectar si es el placeholder del QR
                $is_qr = str_contains( strtolower( $src_raw ), 'qr' ) || ( $obj['name'] ?? '' ) === 'qr_placeholder';
                if ( $is_qr && $qr_path && file_exists( $qr_path ) ) {
                    $src = 'data:image/png;base64,' . base64_encode( file_get_contents( $qr_path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
                } else {
                    $src = self::resolve_image_src( $src_raw );
                }

                $w = (float) ( $obj['width']  ?? 100 ) * (float) ( $obj['scaleX'] ?? 1 );
                $h = (float) ( $obj['height'] ?? 100 ) * (float) ( $obj['scaleY'] ?? 1 );

                if ( $src ) {
                    $html .= sprintf(
                        '<img src="%s" style="%swidth:%spx;height:%spx;">',
                        esc_attr( $src ), $style, $w, $h
                    );
                }

            } elseif ( in_array( $type, [ 'rect', 'circle', 'ellipse' ], true ) ) {
                $fill   = esc_attr( $obj['fill']        ?? 'transparent' );
                $stroke = esc_attr( $obj['stroke']      ?? 'transparent' );
                $sw     = (float) ( $obj['strokeWidth'] ?? 0 );
                $w      = (float) ( $obj['width']  ?? 100 ) * (float) ( $obj['scaleX'] ?? 1 );
                $h      = (float) ( $obj['height'] ?? 100 ) * (float) ( $obj['scaleY'] ?? 1 );

                $radius = $type === 'circle' ? '50%' : '0';
                $html  .= sprintf(
                    '<div style="%swidth:%spx;height:%spx;background-color:%s;border:%spx solid %s;border-radius:%s;"></div>',
                    $style, $w, $h, $fill, $sw, $stroke, $radius
                );
            }
        }

        $html .= '</div></body></html>';

        return $html;
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

        // Solo imágenes permitidas
        $allowed_mime = [ 'image/png', 'image/jpeg', 'image/gif', 'image/webp' ];
        $finfo        = new \finfo( FILEINFO_MIME_TYPE );
        $mime         = $finfo->file( $local_path );

        if ( ! in_array( $mime, $allowed_mime, true ) ) {
            return '';
        }

        $ext_map = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpeg',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];

        $ext = $ext_map[ $mime ] ?? 'png';

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        return 'data:' . $mime . ';base64,' . base64_encode( file_get_contents( $local_path ) );
    }
}
