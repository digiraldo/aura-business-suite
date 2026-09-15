<?php
/**
 * Template: Verificación Pública de Certificado
 *
 * @package AuraBusinessSuite
 * @var array|null $cert  Datos públicos del certificado (de Aura_Certificates_Verify::get_certificate_public_data).
 *                        null si no se encontró.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="aura-cert-verify-wrap">

    <?php if ( is_null( $cert ?? null ) ) : ?>
        <!-- No encontrado -->
        <div class="aura-cert-verify-card aura-cert-verify-card--notfound">
            <div class="aura-cert-verify-icon">❌</div>
            <h2><?php esc_html_e( 'Certificado no encontrado', 'aura-suite' ); ?></h2>
            <p><?php esc_html_e( 'El folio consultado no existe en nuestros registros.', 'aura-suite' ); ?></p>
            <a href="<?php echo esc_url( home_url() ); ?>" class="aura-btn"><?php esc_html_e( '← Volver al inicio', 'aura-suite' ); ?></a>
        </div>

    <?php elseif ( ( $cert['status'] ?? '' ) === 'revoked' ) : ?>
        <!-- Revocado -->
        <div class="aura-cert-verify-card aura-cert-verify-card--revoked">
            <div class="aura-cert-verify-icon">⚠️</div>
            <h2><?php esc_html_e( 'Certificado Revocado', 'aura-suite' ); ?></h2>
            <p>
                <?php printf(
                    /* translators: %s = folio */
                    esc_html__( 'El certificado con folio %s ha sido revocado.', 'aura-suite' ),
                    '<strong>' . esc_html( $cert['folio'] ) . '</strong>'
                ); ?>
            </p>
            <?php if ( ! empty( $cert['revoke_reason'] ) ) : ?>
            <p><strong><?php esc_html_e( 'Motivo:', 'aura-suite' ); ?></strong> <?php echo esc_html( $cert['revoke_reason'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $cert['revoked_at'] ) ) : ?>
            <p><strong><?php esc_html_e( 'Fecha de revocación:', 'aura-suite' ); ?></strong> <?php echo esc_html( $cert['revoked_at'] ); ?></p>
            <?php endif; ?>
        </div>

    <?php else : ?>
        <!-- Válido -->
        <div class="aura-cert-verify-card aura-cert-verify-card--valid">
            <?php if ( ! empty( $cert['org_logo'] ) ) : ?>
            <img class="aura-cert-verify-logo" src="<?php echo esc_url( $cert['org_logo'] ); ?>"
                 alt="<?php echo esc_attr( $cert['organization'] ); ?>">
            <?php endif; ?>

            <div class="aura-cert-verify-icon">✅</div>
            <h1 class="aura-cert-verify-title"><?php esc_html_e( 'Certificado Válido', 'aura-suite' ); ?></h1>

            <div class="aura-cert-verify-data">
                <div class="aura-cert-verify-row">
                    <span class="aura-cert-verify-label"><?php esc_html_e( 'Folio', 'aura-suite' ); ?></span>
                    <span class="aura-cert-verify-value aura-cert-folio-badge"><?php echo esc_html( $cert['folio'] ); ?></span>
                </div>
                <div class="aura-cert-verify-row">
                    <span class="aura-cert-verify-label"><?php esc_html_e( 'Estudiante', 'aura-suite' ); ?></span>
                    <span class="aura-cert-verify-value"><?php echo esc_html( $cert['student_name'] ); ?></span>
                </div>
                <div class="aura-cert-verify-row">
                    <span class="aura-cert-verify-label"><?php esc_html_e( 'Curso', 'aura-suite' ); ?></span>
                    <span class="aura-cert-verify-value"><?php echo esc_html( $cert['course_name'] ); ?></span>
                </div>
                <?php if ( ! empty( $cert['program_name'] ) ) : ?>
                <div class="aura-cert-verify-row">
                    <span class="aura-cert-verify-label"><?php esc_html_e( 'Programa', 'aura-suite' ); ?></span>
                    <span class="aura-cert-verify-value"><?php echo esc_html( $cert['program_name'] ); ?></span>
                </div>
                <?php endif; ?>
                <div class="aura-cert-verify-row">
                    <span class="aura-cert-verify-label"><?php esc_html_e( 'Emitido por', 'aura-suite' ); ?></span>
                    <span class="aura-cert-verify-value"><?php echo esc_html( $cert['organization'] ); ?></span>
                </div>
                <div class="aura-cert-verify-row">
                    <span class="aura-cert-verify-label"><?php esc_html_e( 'Fecha de Emisión', 'aura-suite' ); ?></span>
                    <span class="aura-cert-verify-value"><?php echo esc_html( $cert['issued_at'] ); ?></span>
                </div>
            </div>

            <p class="aura-cert-verify-footer">
                <?php esc_html_e( 'Este certificado es auténtico y ha sido verificado digitalmente.', 'aura-suite' ); ?>
            </p>
        </div>

    <?php endif; ?>

    <!-- Formulario de búsqueda por folio -->
    <div class="aura-cert-verify-search">
        <h3><?php esc_html_e( 'Verificar otro certificado', 'aura-suite' ); ?></h3>
        <form method="GET" action="" style="display:flex;gap:8px;">
            <input type="text" name="folio"
                   class="aura-input"
                   placeholder="<?php esc_attr_e( 'Ingrese el folio (ej: CEM-2026-0042)', 'aura-suite' ); ?>"
                   pattern="^[A-Z]{1,10}-\d{4}-\d+$"
                   style="flex:1;">
            <button type="submit" class="button button-primary">
                <?php esc_html_e( 'Verificar', 'aura-suite' ); ?>
            </button>
        </form>
    </div>
</div>
