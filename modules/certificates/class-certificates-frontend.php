<?php
/**
 * Integración Frontend — Portal del Estudiante
 *
 * Renderiza la pestaña "Mis Certificados" en el portal del estudiante.
 *
 * @package AuraBusinessSuite
 * @subpackage Certificates
 * @since 1.7.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Certificates_Frontend {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        // Registrar pestaña en el portal estudiantil
        add_action( 'aura_render_student_certificates_tab', [ __CLASS__, 'render_student_tab' ], 10, 2 );
        // Encolar assets frontend
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ] );
        // Shortcode [aura_mis_certificados]
        add_shortcode( 'aura_mis_certificados', [ __CLASS__, 'shortcode_mis_certificados' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // SHORTCODE [aura_mis_certificados]
    // ─────────────────────────────────────────────────────────────

    /**
     * Shortcode que muestra la lista de certificados del usuario logueado.
     *
     * @return string HTML de salida.
     */
    public static function shortcode_mis_certificados(): string {
        if ( ! is_user_logged_in() ) {
            return '<div class="aura-notice aura-notice-warning"><p>' .
                   esc_html__( 'Debes iniciar sesión para ver tus certificados.', 'aura-suite' ) .
                   '</p></div>';
        }

        $wp_user_id = get_current_user_id();

        global $wpdb;
        $students_table = $wpdb->prefix . 'aura_students';
        $student_id     = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$students_table} WHERE wp_user_id = %d LIMIT 1", $wp_user_id )
        );

        if ( ! $student_id ) {
            return '<div class="aura-notice"><p>' .
                   esc_html__( 'No se encontró un perfil de estudiante vinculado a tu cuenta.', 'aura-suite' ) .
                   '</p></div>';
        }

        // Reutiliza la misma consulta que render_student_tab
        $table        = $wpdb->prefix . 'aura_certificates';
        $certificates = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT folio, course_name, program_name, issued_at, status, verify_url
                 FROM {$table}
                 WHERE student_id = %d AND status = 'active'
                 ORDER BY issued_at DESC",
                $student_id
            )
        );

        $grouped = [];
        foreach ( ( $certificates ?: [] ) as $cert ) {
            $program = $cert->program_name ?: __( 'General', 'aura-suite' );
            $grouped[ $program ][] = [
                'folio'        => $cert->folio,
                'course_name'  => $cert->course_name,
                'issued_at'    => date_i18n( get_option( 'date_format' ), strtotime( $cert->issued_at ) ),
                'status'       => $cert->status,
                'verify_url'   => $cert->verify_url,
                'download_url' => add_query_arg(
                    [
                        'action' => 'aura_cert_download',
                        'folio'  => $cert->folio,
                        'nonce'  => wp_create_nonce( 'aura_download_' . $cert->folio ),
                    ],
                    admin_url( 'admin-ajax.php' )
                ),
            ];
        }

        ob_start();
        $template = AURA_PLUGIN_DIR . 'templates/certificates/frontend/my-certificates.php';
        if ( file_exists( $template ) ) {
            include $template;
        } else {
            self::render_inline_tab( $grouped );
        }
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────
    // ASSETS FRONTEND
    // ─────────────────────────────────────────────────────────────

    public static function enqueue_frontend_assets(): void {
        if ( ! is_page() && ! has_shortcode( get_post_field( 'post_content', get_the_ID() ), 'aura_portal_estudiante' ) ) {
            return;
        }

        $plugin_url     = defined( 'AURA_PLUGIN_URL' ) ? AURA_PLUGIN_URL : plugin_dir_url( dirname( dirname( __DIR__ ) ) . '/aura-business-suite.php' );
        $plugin_version = defined( 'AURA_VERSION' ) ? AURA_VERSION : ( defined( 'AURA_SUITE_VERSION' ) ? AURA_SUITE_VERSION : '1.0.0' );

        wp_enqueue_style(
            'aura-certificates-frontend',
            $plugin_url . 'assets/css/certificates.css',
            [],
            $plugin_version
        );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER PESTAÑA
    // ─────────────────────────────────────────────────────────────

    /**
     * Renderiza el contenido de la pestaña "Mis Certificados" en el portal.
     *
     * @param int $student_id  ID del estudiante en la tabla aura_students.
     * @param int $wp_user_id  ID del usuario de WordPress.
     */
    public static function render_student_tab( int $student_id, int $wp_user_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificates';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $certificates = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT folio, course_name, program_name, issued_at, status, verify_url
                 FROM {$table}
                 WHERE student_id = %d AND status = 'active'
                 ORDER BY issued_at DESC",
                $student_id
            )
        );

        // Agrupar por programa
        $grouped = [];
        foreach ( ( $certificates ?: [] ) as $cert ) {
            $program = $cert->program_name ?: __( 'General', 'aura-suite' );
            $grouped[ $program ][] = [
                'folio'       => $cert->folio,
                'course_name' => $cert->course_name,
                'issued_at'   => date_i18n( get_option( 'date_format' ), strtotime( $cert->issued_at ) ),
                'status'      => $cert->status,
                'verify_url'  => $cert->verify_url,
                'download_url'=> add_query_arg(
                    [
                        'action' => 'aura_cert_download',
                        'folio'  => $cert->folio,
                        'nonce'  => wp_create_nonce( 'aura_download_' . $cert->folio ),
                    ],
                    admin_url( 'admin-ajax.php' )
                ),
            ];
        }

        $template = plugin_dir_path( dirname( __DIR__ ) . '/templates/' ) . '../templates/certificates/frontend/my-certificates.php';

        if ( file_exists( $template ) ) {
            include $template;
        } else {
            self::render_inline_tab( $grouped );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // FALLBACK INLINE
    // ─────────────────────────────────────────────────────────────

    /**
     * Render inline de respaldo si no existe la plantilla.
     *
     * @param array $grouped Certificados agrupados por programa.
     */
    private static function render_inline_tab( array $grouped ): void {
        if ( empty( $grouped ) ) {
            echo '<p class="aura-no-certs">' . esc_html__( 'Aún no tiene certificados emitidos.', 'aura-suite' ) . '</p>';
            return;
        }

        echo '<div class="aura-my-certificates">';
        foreach ( $grouped as $program => $certs ) {
            echo '<h3 class="aura-cert-program">' . esc_html( $program ) . '</h3>';
            echo '<div class="aura-cert-list">';
            foreach ( $certs as $cert ) {
                echo '<div class="aura-cert-item">';
                echo '<span class="aura-cert-course">' . esc_html( $cert['course_name'] ) . '</span>';
                echo '<span class="aura-cert-date">' . esc_html( $cert['issued_at'] ) . '</span>';
                echo '<div class="aura-cert-actions">';
                echo '<a href="' . esc_url( $cert['download_url'] ) . '" class="aura-btn aura-btn-sm">' . esc_html__( 'Descargar PDF', 'aura-suite' ) . '</a>';
                echo '<a href="' . esc_url( $cert['verify_url'] ) . '" class="aura-btn aura-btn-sm aura-btn-outline" target="_blank">' . esc_html__( 'Verificar', 'aura-suite' ) . '</a>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
}
