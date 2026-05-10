<?php
/**
 * Verificación Pública de Certificados
 *
 * Intercepta la URL /{verify_slug}/{folio} y muestra datos públicos del certificado.
 *
 * @package AuraBusinessSuite
 * @subpackage Certificates
 * @since 1.7.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Certificates_Verify {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'template_redirect',    [ __CLASS__, 'intercept_verify_page' ] );
        add_shortcode( 'aura_verificar_certificado', [ __CLASS__, 'shortcode_verificar' ] );
        // Endpoint AJAX para verificación vía JS (API pública)
        add_action( 'wp_ajax_nopriv_aura_cert_verify_public', [ __CLASS__, 'ajax_verify_public' ] );
        add_action( 'wp_ajax_aura_cert_verify_public',        [ __CLASS__, 'ajax_verify_public' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // INTERCEPTOR DE URL
    // ─────────────────────────────────────────────────────────────

    /**
     * Detecta la URL de verificación y renderiza la plantilla pública.
     * Ejemplo: /verificar-certificado/CEM-2026-0042
     */
    public static function intercept_verify_page(): void {
        $folio = get_query_var( 'aura_cert_verify' );

        if ( empty( $folio ) ) {
            return;
        }

        $folio = sanitize_text_field( $folio );

        // Renderizar con la plantilla pública
        status_header( 200 );
        self::render_verify_page( $folio );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // SHORTCODE
    // ─────────────────────────────────────────────────────────────

    /**
     * Shortcode [aura_verificar_certificado folio="CEM-2026-0042"]
     * O si no se provee folio, muestra un formulario de búsqueda.
     */
    public static function shortcode_verificar( array $atts ): string {
        $atts  = shortcode_atts( [ 'folio' => '' ], $atts );
        $folio = sanitize_text_field( $atts['folio'] );

        // También intentar leer de la URL querystring: ?folio=CEM-2026-0042
        if ( empty( $folio ) && ! empty( $_GET['folio'] ) ) {
            $folio = sanitize_text_field( wp_unslash( $_GET['folio'] ) );
        }

        ob_start();

        if ( ! empty( $folio ) ) {
            $cert = self::get_certificate_public_data( $folio );
            include self::template_path( 'verify-public.php' );
        } else {
            include self::template_path( 'verify-search.php' );
        }

        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Verificar folio (API pública)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_verify_public(): void {
        $folio = sanitize_text_field( wp_unslash( $_REQUEST['folio'] ?? '' ) );

        if ( empty( $folio ) ) {
            wp_send_json_error( [ 'message' => __( 'Folio no especificado.', 'aura-suite' ) ], 400 );
        }

        $cert = self::get_certificate_public_data( $folio );

        if ( $cert === null ) {
            wp_send_json_error( [
                'valid'   => false,
                'message' => __( 'El certificado no fue encontrado.', 'aura-suite' ),
            ], 404 );
        }

        wp_send_json_success( array_merge( $cert, [ 'valid' => $cert['status'] === 'active' ] ) );
    }

    // ─────────────────────────────────────────────────────────────
    // DATOS PÚBLICOS DEL CERTIFICADO
    // ─────────────────────────────────────────────────────────────

    /**
     * Retorna SOLO los datos públicos de un certificado por folio.
     * Nunca expone pdf_path, ip, uuid interno u otros datos sensibles.
     *
     * @param string $folio
     * @return array|null null si no existe.
     */
    public static function get_certificate_public_data( string $folio ): ?array {
        global $wpdb;

        if ( ! Aura_Certificates_Folio::is_valid_format( $folio ) ) {
            return null;
        }

        $certs_table    = $wpdb->prefix . 'aura_certificates';
        $students_table = $wpdb->prefix . 'aura_students';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $cert = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT c.folio, c.course_name, c.program_name, c.issued_at, c.status, c.revoke_reason, c.revoked_at, c.student_id
                 FROM {$certs_table} c
                 WHERE c.folio = %s",
                $folio
            )
        );

        if ( ! $cert ) {
            return null;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $student = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT first_name, last_name FROM {$students_table} WHERE id = %d",
                $cert->student_id
            )
        );

        $org_name = Aura_Certificates_Settings::get( 'org_name', get_option( 'blogname', '' ) );
        $org_logo = Aura_Certificates_Settings::get( 'org_logo_url', '' );

        return [
            'folio'           => $cert->folio,
            'student_name'    => $student
                                    ? trim( $student->first_name . ' ' . $student->last_name )
                                    : '',
            'course_name'     => $cert->course_name,
            'program_name'    => $cert->program_name,
            'issued_at'       => $cert->issued_at
                                    ? date_i18n( get_option( 'date_format' ), strtotime( $cert->issued_at ) )
                                    : '',
            'status'          => $cert->status,
            'revoke_reason'   => $cert->status === 'revoked' ? $cert->revoke_reason : '',
            'revoked_at'      => $cert->status === 'revoked' && $cert->revoked_at
                                    ? date_i18n( get_option( 'date_format' ), strtotime( $cert->revoked_at ) )
                                    : '',
            'organization'    => $org_name,
            'org_logo'        => $org_logo,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────

    /**
     * Renderiza la página completa de verificación (fuera del loop de WP).
     *
     * @param string $folio
     */
    private static function render_verify_page( string $folio ): void {
        $cert = self::get_certificate_public_data( $folio );
        get_header();
        include self::template_path( 'verify-public.php' );
        get_footer();
    }

    // ─────────────────────────────────────────────────────────────
    // UTILS
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve la ruta absoluta a una plantilla del módulo.
     * Busca primero en el tema activo (sobreescritura), luego en el plugin.
     *
     * @param string $filename
     * @return string
     */
    private static function template_path( string $filename ): string {
        $theme_path  = get_stylesheet_directory() . '/aura-certificates/' . $filename;
        $plugin_path = plugin_dir_path( __DIR__ ) . '../templates/certificates/' . $filename;

        return file_exists( $theme_path ) ? $theme_path : $plugin_path;
    }
}
