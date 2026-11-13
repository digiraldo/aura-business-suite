<?php
/**
 * Template: Detalle de Certificado Emitido
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table          = $wpdb->prefix . 'aura_certificates';
$students_table = $wpdb->prefix . 'aura_students';
$tpl_table      = $wpdb->prefix . 'aura_certificate_templates';
$tp_table       = $wpdb->prefix . 'aura_third_parties';

$cert_id = absint( $_GET['id'] ?? 0 );
$folio   = sanitize_text_field( wp_unslash( $_GET['folio'] ?? '' ) );
$list_url = admin_url( 'admin.php?page=aura-certificates-list' );

if ( $cert_id > 0 ) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $cert = $wpdb->get_row( $wpdb->prepare(
        "SELECT c.*,
                COALESCE(NULLIF(c.student_name,''), TRIM(CONCAT(COALESCE(s.first_name,''), ' ', COALESCE(s.last_name,'')))) AS display_name,
                COALESCE(NULLIF(c.recipient_email,''), s.email, u.user_email, '') AS display_email,
                t.name AS template_name,
                tp.commercial_name AS third_party_name
         FROM {$table} c
         LEFT JOIN {$students_table} s ON s.id = c.student_id
         LEFT JOIN {$tpl_table} t ON t.id = c.template_id
         LEFT JOIN {$wpdb->users} u ON u.ID = c.wp_user_id
         LEFT JOIN {$tp_table} tp ON tp.id = c.third_party_id
         WHERE c.id = %d",
        $cert_id
    ) );
} elseif ( $folio !== '' ) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $cert = $wpdb->get_row( $wpdb->prepare(
        "SELECT c.*,
                COALESCE(NULLIF(c.student_name,''), TRIM(CONCAT(COALESCE(s.first_name,''), ' ', COALESCE(s.last_name,'')))) AS display_name,
                COALESCE(NULLIF(c.recipient_email,''), s.email, u.user_email, '') AS display_email,
                t.name AS template_name,
                tp.commercial_name AS third_party_name
         FROM {$table} c
         LEFT JOIN {$students_table} s ON s.id = c.student_id
         LEFT JOIN {$tpl_table} t ON t.id = c.template_id
         LEFT JOIN {$wpdb->users} u ON u.ID = c.wp_user_id
         LEFT JOIN {$tp_table} tp ON tp.id = c.third_party_id
         WHERE c.folio = %s",
        $folio
    ) );
} else {
    $cert = null;
}

if ( ! $cert ) : ?>
<div class="wrap aura-certificates-wrap">
    <div class="notice notice-error inline"><p><?php esc_html_e( 'Certificado no encontrado.', 'aura-suite' ); ?></p></div>
    <a href="<?php echo esc_url( $list_url ); ?>" class="button">← <?php esc_html_e( 'Volver al listado', 'aura-suite' ); ?></a>
</div>
<?php return; endif;

$type_labels = [
    'student'     => [ 'label' => __( 'Estudiante', 'aura-suite' ), 'class' => 'aura-cert-badge--student' ],
    'wp_user'     => [ 'label' => __( 'Usuario WP', 'aura-suite' ), 'class' => 'aura-cert-badge--wpuser' ],
    'third_party' => [ 'label' => __( 'Tercero', 'aura-suite' ),    'class' => 'aura-cert-badge--thirdparty' ],
    'manual'      => [ 'label' => __( 'Manual', 'aura-suite' ),     'class' => 'aura-cert-badge--manual' ],
];
$rtype = $cert->recipient_type ?: ( ! empty( $cert->student_id ) ? 'student' : ( ! empty( $cert->wp_user_id ) ? 'wp_user' : ( ! empty( $cert->third_party_id ) ? 'third_party' : 'manual' ) ) );
$rinfo = $type_labels[ $rtype ] ?? $type_labels['student'];

$download_url = admin_url( 'admin-ajax.php?action=aura_cert_download&folio=' . rawurlencode( $cert->folio ) );
$verify_url   = ! empty( $cert->verify_url ) ? $cert->verify_url : Aura_Certificates_Verify::get_verify_url( $cert->folio );

$qr_url = '';
if ( ! empty( $cert->qr_path ) && file_exists( $cert->qr_path ) ) {
    $upload = wp_upload_dir();
    $qr_url = str_replace( wp_normalize_path( $upload['basedir'] ), $upload['baseurl'], wp_normalize_path( $cert->qr_path ) );
}
?>
<div class="wrap aura-certificates-wrap" id="aura-cert-view-page">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div>
            <h1 class="wp-heading-inline" style="margin:0;">
                📜 <?php printf( esc_html__( 'Certificado %s', 'aura-suite' ), '<code>' . esc_html( $cert->folio ) . '</code>' ); ?>
            </h1>
            <span class="aura-cert-status aura-cert-status--<?php echo esc_attr( $cert->status ); ?>" style="margin-left:8px;">
                <?php echo $cert->status === 'active' ? esc_html__( 'Activo', 'aura-suite' ) : esc_html__( 'Revocado', 'aura-suite' ); ?>
            </span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo esc_url( $list_url ); ?>" class="button">← <?php esc_html_e( 'Volver al listado', 'aura-suite' ); ?></a>
            <a href="<?php echo esc_url( $download_url ); ?>" class="button button-primary" target="_blank">
                ⬇ <?php esc_html_e( 'Descargar PDF', 'aura-suite' ); ?>
            </a>
            <?php if ( ! empty( $cert->display_email ) && $cert->status === 'active' ) : ?>
            <button type="button" id="aura-cert-resend-email-btn" class="button" data-id="<?php echo esc_attr( $cert->id ); ?>">
                ✉️ <?php esc_html_e( 'Reenviar Correo', 'aura-suite' ); ?>
            </button>
            <?php endif; ?>
            <?php if ( $cert->status === 'active' && ( current_user_can( 'aura_cert_revoke' ) || current_user_can( 'manage_options' ) ) ) : ?>
            <button type="button" class="button aura-cert-revoke-btn"
                    style="color:#dc2626;border-color:#fca5a5;"
                    data-id="<?php echo esc_attr( $cert->id ); ?>"
                    data-folio="<?php echo esc_attr( $cert->folio ); ?>">
                🚫 <?php esc_html_e( 'Revocar', 'aura-suite' ); ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="aura-issue-layout">
        <div class="aura-issue-main">
            <div class="aura-card" style="margin-bottom:20px;">
                <h2 style="margin-top:0;"><?php esc_html_e( 'Información del Certificado', 'aura-suite' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e( 'Destinatario', 'aura-suite' ); ?></th>
                        <td>
                            <strong style="font-size:15px;"><?php echo esc_html( $cert->display_name ); ?></strong>
                            <span class="aura-cert-badge <?php echo esc_attr( $rinfo['class'] ); ?>" style="margin-left:8px;">
                                <?php echo esc_html( $rinfo['label'] ); ?>
                            </span>
                            <?php if ( ! empty( $cert->third_party_name ) && $cert->third_party_name !== $cert->display_name ) : ?>
                                <div class="description"><?php printf( esc_html__( 'Tercero vinculado: %s', 'aura-suite' ), esc_html( $cert->third_party_name ) ); ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Documento / Correo', 'aura-suite' ); ?></th>
                        <td>
                            <?php echo ! empty( $cert->recipient_document ) ? esc_html( $cert->recipient_document ) : '—'; ?>
                            &nbsp;•&nbsp;
                            <?php echo ! empty( $cert->display_email ) ? esc_html( $cert->display_email ) : '—'; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Curso / Evento', 'aura-suite' ); ?></th>
                        <td><strong><?php echo esc_html( $cert->course_name ); ?></strong></td>
                    </tr>
                    <?php if ( ! empty( $cert->program_name ) ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Programa', 'aura-suite' ); ?></th>
                        <td><?php echo esc_html( $cert->program_name ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php esc_html_e( 'Detalles Académicos', 'aura-suite' ); ?></th>
                        <td>
                            <?php
                            $meta_parts = array_filter([
                                ! empty( $cert->category ) ? __( 'Categoría:', 'aura-suite' ) . ' ' . $cert->category : '',
                                ! empty( $cert->hours ) ? __( 'Duración:', 'aura-suite' ) . ' ' . $cert->hours : '',
                                ! empty( $cert->grade ) ? __( 'Calificación:', 'aura-suite' ) . ' ' . $cert->grade : '',
                                ! empty( $cert->city ) ? __( 'Ciudad:', 'aura-suite' ) . ' ' . $cert->city : '',
                            ]);
                            echo ! empty( $meta_parts ) ? esc_html( implode( '  |  ', $meta_parts ) ) : '—';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Instructor', 'aura-suite' ); ?></th>
                        <td><?php echo ! empty( $cert->instructor_name ) ? esc_html( $cert->instructor_name ) : '—'; ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Plantilla Utilizada', 'aura-suite' ); ?></th>
                        <td><?php echo esc_html( $cert->template_name ?: __( 'Predeterminada', 'aura-suite' ) ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Fecha de Emisión', 'aura-suite' ); ?></th>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $cert->issued_at ) ) ); ?></td>
                    </tr>
                    <?php if ( ! empty( $cert->description ) ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Descripción', 'aura-suite' ); ?></th>
                        <td><?php echo esc_html( $cert->description ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $cert->status === 'revoked' ) : ?>
                    <tr>
                        <th style="color:#dc2626;"><?php esc_html_e( 'Motivo de Revocación', 'aura-suite' ); ?></th>
                        <td style="color:#991b1b;">
                            <?php echo esc_html( $cert->revoke_reason ?: __( 'Sin motivo especificado', 'aura-suite' ) ); ?>
                            <?php if ( ! empty( $cert->revoked_at ) ) : ?>
                                <div class="description"><?php echo esc_html( $cert->revoked_at ); ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="aura-issue-side">
            <div class="aura-card" style="text-align:center;">
                <h2 style="margin-top:0;"><?php esc_html_e( 'Verificación Pública', 'aura-suite' ); ?></h2>
                <?php if ( $qr_url ) : ?>
                <div style="margin:12px auto;">
                    <img src="<?php echo esc_url( $qr_url ); ?>" alt="QR <?php echo esc_attr( $cert->folio ); ?>"
                         style="width:160px;height:160px;border:1px solid #e2e8f0;border-radius:8px;padding:6px;background:#fff;">
                </div>
                <?php endif; ?>
                <p style="font-size:12px;color:#64748b;word-break:break-all;margin:8px 0;">
                    <a href="<?php echo esc_url( $verify_url ); ?>" target="_blank"><?php echo esc_html( $verify_url ); ?></a>
                </p>
                <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:10px;">
                    <button type="button" id="aura-cert-copy-verify-btn" class="button" data-url="<?php echo esc_attr( $verify_url ); ?>">
                        📋 <?php esc_html_e( 'Copiar enlace', 'aura-suite' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $verify_url ); ?>" target="_blank" class="button">
                        🔗 <?php esc_html_e( 'Abrir portal', 'aura-suite' ); ?>
                    </a>
                </div>
                <hr style="margin:16px 0;">
                <p style="font-size:11px;color:#64748b;margin:0;">
                    <?php esc_html_e( 'Código de Verificación / UUID:', 'aura-suite' ); ?><br>
                    <code><?php echo esc_html( $cert->uuid ?: $cert->folio ); ?></code>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Modal de revocación -->
<div id="aura-revoke-modal" class="aura-modal" style="display:none;">
    <div class="aura-modal-overlay aura-modal-close"></div>
    <div class="aura-modal-content">
        <h2><?php esc_html_e( 'Revocar Certificado', 'aura-suite' ); ?> <span id="aura-revoke-folio-label"></span></h2>
        <input type="hidden" id="aura-revoke-id" value="">
        <p><?php esc_html_e( 'Indique el motivo de la revocación (obligatorio):', 'aura-suite' ); ?></p>
        <textarea id="aura-revoke-reason" class="aura-input" rows="3" style="width:100%;"
                  placeholder="<?php esc_attr_e( 'Motivo de revocación…', 'aura-suite' ); ?>"></textarea>
        <div class="aura-modal-actions" style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" id="aura-revoke-cancel" class="button aura-modal-close"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="aura-revoke-confirm" class="button button-primary" style="background:#dc2626;border-color:#dc2626;">
                <?php esc_html_e( 'Confirmar Revocación', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>

