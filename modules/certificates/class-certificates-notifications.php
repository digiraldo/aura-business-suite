<?php
/**
 * Notificaciones del Módulo de Certificados
 *
 * Envía emails y mensajes WhatsApp relacionados a la emisión y revocación.
 *
 * @package AuraBusinessSuite
 * @subpackage Certificates
 * @since 1.7.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Certificates_Notifications {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        // No usa hooks propios; es invocado por Issuer.
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL: Certificado emitido
    // ─────────────────────────────────────────────────────────────

    /**
     * Envía email al estudiante notificando la emisión del certificado.
     *
     * @param int $certificate_id
     */
    public static function send_issued_email( int $certificate_id ): void {
        $data = self::get_notification_data( $certificate_id );
        if ( ! $data ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s = nombre del curso */
            __( 'Tu certificado de %s está listo', 'aura-suite' ),
            $data['course_name']
        );

        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2><?php echo esc_html( $data['org_name'] ); ?></h2>
            <p><?php
                printf(
                    /* translators: %s = nombre del estudiante */
                    esc_html__( 'Estimado/a %s,', 'aura-suite' ),
                    esc_html( $data['student_name'] )
                );
            ?></p>
            <p><?php
                printf(
                    /* translators: %s = nombre del curso */
                    esc_html__( 'Nos complace informarte que tu certificado del curso "%s" ha sido emitido.', 'aura-suite' ),
                    esc_html( $data['course_name'] )
                );
            ?></p>
            <p><strong><?php esc_html_e( 'Folio:', 'aura-suite' ); ?></strong> <?php echo esc_html( $data['folio'] ); ?></p>
            <p>
                <a href="<?php echo esc_url( $data['download_url'] ); ?>" style="background:#8b5cf6;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;">
                    <?php esc_html_e( 'Descargar Certificado', 'aura-suite' ); ?>
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url( $data['verify_url'] ); ?>">
                    <?php esc_html_e( 'Verificar Certificado en Línea', 'aura-suite' ); ?>
                </a>
            </p>
            <p style="color:#666;font-size:12px;"><?php esc_html_e( 'Este mensaje fue generado automáticamente.', 'aura-suite' ); ?></p>
        </div>
        <?php
        $body = ob_get_clean();

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . esc_html( $data['org_name'] ) . ' <' . esc_attr( get_option( 'admin_email' ) ) . '>',
        ];

        $attachments = [];
        if ( ! empty( $data['pdf_path'] ) && file_exists( $data['pdf_path'] ) ) {
            $attachments[] = $data['pdf_path'];
        }

        wp_mail( $data['student_email'], $subject, $body, $headers, $attachments );

        // Si hay integración con Aura_Notifications, también pasa por allí
        if ( class_exists( 'Aura_Notifications' ) ) {
            Aura_Notifications::send_email( $data['student_email'], $subject, $body, [
                'attachments' => $attachments,
            ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // WHATSAPP: Certificado emitido
    // ─────────────────────────────────────────────────────────────

    /**
     * Envía notificación WhatsApp al estudiante.
     *
     * @param int $certificate_id
     */
    public static function send_issued_whatsapp( int $certificate_id ): void {
        if ( ! class_exists( 'Aura_Notifications' ) ) {
            return;
        }

        $data = self::get_notification_data( $certificate_id );
        if ( ! $data || empty( $data['student_phone'] ) ) {
            return;
        }

        $message = sprintf(
            /* translators: 1=nombre estudiante, 2=nombre curso, 3=folio, 4=URL descarga, 5=URL verificar */
            __( "Hola %1\$s,\n\nTu certificado del curso *%2\$s* ha sido emitido con folio *%3\$s*.\n\n📥 Descargar: %4\$s\n🔎 Verificar: %5\$s", 'aura-suite' ),
            $data['student_name'],
            $data['course_name'],
            $data['folio'],
            $data['download_url'],
            $data['verify_url']
        );

        Aura_Notifications::send_whatsapp( $data['student_phone'], $message );
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL: Certificado revocado
    // ─────────────────────────────────────────────────────────────

    /**
     * Notifica a estudiante y administrador sobre la revocación.
     *
     * @param int $certificate_id
     */
    public static function send_revoke_email( int $certificate_id ): void {
        $data = self::get_notification_data( $certificate_id );
        if ( ! $data ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s = folio */
            __( 'Certificado %s revocado', 'aura-suite' ),
            $data['folio']
        );

        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color:#dc2626;"><?php esc_html_e( 'Certificado Revocado', 'aura-suite' ); ?></h2>
            <p><?php
                printf(
                    /* translators: %s = nombre del estudiante */
                    esc_html__( 'Estimado/a %s,', 'aura-suite' ),
                    esc_html( $data['student_name'] )
                );
            ?></p>
            <p><?php
                printf(
                    /* translators: 1=folio, 2=nombre del curso */
                    esc_html__( 'Le informamos que el certificado con folio %1$s correspondiente al curso "%2$s" ha sido revocado.', 'aura-suite' ),
                    esc_html( $data['folio'] ),
                    esc_html( $data['course_name'] )
                );
            ?></p>
            <?php if ( ! empty( $data['revoke_reason'] ) ) : ?>
            <p><strong><?php esc_html_e( 'Motivo:', 'aura-suite' ); ?></strong> <?php echo esc_html( $data['revoke_reason'] ); ?></p>
            <?php endif; ?>
            <p style="color:#666;font-size:12px;"><?php esc_html_e( 'Para más información, comuníquese con la institución.', 'aura-suite' ); ?></p>
        </div>
        <?php
        $body    = ob_get_clean();
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        // Notificar al estudiante
        if ( ! empty( $data['student_email'] ) ) {
            wp_mail( $data['student_email'], $subject, $body, $headers );
        }

        // Notificar al administrador
        wp_mail( get_option( 'admin_email' ), '[Admin] ' . $subject, $body, $headers );
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL: Emisión masiva completada
    // ─────────────────────────────────────────────────────────────

    /**
     * Notifica al administrador que un batch de emisión masiva finalizó.
     *
     * @param int   $admin_user_id
     * @param array $summary ['total', 'success', 'failed']
     */
    public static function send_bulk_complete_email( int $admin_user_id, array $summary ): void {
        $admin = get_user_by( 'id', $admin_user_id );
        if ( ! $admin ) {
            return;
        }

        $subject = __( 'Emisión masiva de certificados completada', 'aura-suite' );

        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2><?php esc_html_e( 'Procesamiento Masivo Completado', 'aura-suite' ); ?></h2>
            <p>
                <?php esc_html_e( 'El proceso de emisión masiva de certificados ha finalizado.', 'aura-suite' ); ?>
            </p>
            <table style="border-collapse:collapse;width:100%;">
                <tr>
                    <td style="padding:8px;border:1px solid #eee;"><?php esc_html_e( 'Total procesados', 'aura-suite' ); ?></td>
                    <td style="padding:8px;border:1px solid #eee;"><?php echo (int) $summary['total']; ?></td>
                </tr>
                <tr>
                    <td style="padding:8px;border:1px solid #eee;color:#16a34a;"><?php esc_html_e( 'Exitosos', 'aura-suite' ); ?></td>
                    <td style="padding:8px;border:1px solid #eee;"><?php echo (int) $summary['success']; ?></td>
                </tr>
                <tr>
                    <td style="padding:8px;border:1px solid #eee;color:#dc2626;"><?php esc_html_e( 'Con error', 'aura-suite' ); ?></td>
                    <td style="padding:8px;border:1px solid #eee;"><?php echo (int) $summary['failed']; ?></td>
                </tr>
            </table>
        </div>
        <?php
        $body = ob_get_clean();

        wp_mail( $admin->user_email, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPER
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene todos los datos necesarios para una notificación.
     *
     * @param int $certificate_id
     * @return array|null
     */
    private static function get_notification_data( int $certificate_id ): ?array {
        global $wpdb;

        $certs_table    = $wpdb->prefix . 'aura_certificates';
        $students_table = $wpdb->prefix . 'aura_students';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $cert = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$certs_table} WHERE id = %d", $certificate_id )
        );

        if ( ! $cert ) {
            return null;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $student = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$students_table} WHERE id = %d", $cert->student_id )
        );

        if ( ! $student ) {
            return null;
        }

        $org_name = Aura_Certificates_Settings::get( 'org_name', get_option( 'blogname', '' ) );

        // Email del estudiante: via WP user si existe, o campo directo
        $student_email = '';
        if ( $student->wp_user_id ) {
            $wp_user = get_user_by( 'id', (int) $student->wp_user_id );
            if ( $wp_user ) {
                $student_email = $wp_user->user_email;
            }
        }
        if ( empty( $student_email ) && ! empty( $student->email ) ) {
            $student_email = $student->email;
        }

        $student_phone = $student->phone ?? '';

        // URL de descarga: admin-ajax con nonce
        $download_url = add_query_arg(
            [
                'action' => 'aura_cert_download',
                'folio'  => $cert->folio,
                'nonce'  => wp_create_nonce( 'aura_download_' . $cert->folio ),
            ],
            admin_url( 'admin-ajax.php' )
        );

        return [
            'certificate_id' => $certificate_id,
            'folio'          => $cert->folio,
            'course_name'    => $cert->course_name,
            'program_name'   => $cert->program_name,
            'issued_at'      => $cert->issued_at,
            'status'         => $cert->status,
            'revoke_reason'  => $cert->revoke_reason ?? '',
            'pdf_path'       => $cert->pdf_path,
            'verify_url'     => $cert->verify_url,
            'download_url'   => $download_url,
            'student_name'   => trim( $student->first_name . ' ' . $student->last_name ),
            'student_email'  => $student_email,
            'student_phone'  => $student_phone,
            'org_name'       => $org_name,
        ];
    }
}
