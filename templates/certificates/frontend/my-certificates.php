<?php
/**
 * Template: Mis Certificados (Portal del Estudiante)
 *
 * Este template es incluido desde Aura_Certificates_Frontend::render_student_tab().
 *
 * @package AuraBusinessSuite
 * @var array $grouped  Certificados agrupados por programa: [ 'Programa' => [ [...], ... ] ]
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="aura-my-certificates">

    <?php if ( empty( $grouped ) ) : ?>
        <div class="aura-my-certs-empty">
            <span class="aura-empty-icon">🏅</span>
            <p><?php esc_html_e( 'Aún no tienes certificados emitidos.', 'aura-suite' ); ?></p>
        </div>
    <?php else : ?>

        <?php foreach ( $grouped as $program => $certs ) : ?>
        <div class="aura-my-certs-group">
            <h3 class="aura-my-certs-program"><?php echo esc_html( $program ); ?></h3>

            <div class="aura-my-certs-list">
                <?php foreach ( $certs as $cert ) : ?>
                <div class="aura-my-cert-card">
                    <div class="aura-my-cert-icon">🏅</div>
                    <div class="aura-my-cert-info">
                        <h4 class="aura-my-cert-course"><?php echo esc_html( $cert['course_name'] ); ?></h4>
                        <p class="aura-my-cert-meta">
                            <span><?php esc_html_e( 'Emitido:', 'aura-suite' ); ?> <?php echo esc_html( $cert['issued_at'] ); ?></span>
                            <span> · Folio: <code><?php echo esc_html( $cert['folio'] ); ?></code></span>
                        </p>
                    </div>
                    <div class="aura-my-cert-actions">
                        <a href="<?php echo esc_url( $cert['download_url'] ); ?>"
                           class="aura-btn aura-btn-sm"
                           download>
                            ⬇ <?php esc_html_e( 'Descargar PDF', 'aura-suite' ); ?>
                        </a>
                        <a href="<?php echo esc_url( $cert['verify_url'] ); ?>"
                           class="aura-btn aura-btn-sm aura-btn-outline"
                           target="_blank" rel="noopener">
                            🔎 <?php esc_html_e( 'Verificar', 'aura-suite' ); ?>
                        </a>
                        <button type="button"
                                class="aura-btn aura-btn-sm aura-btn-outline aura-copy-verify-url"
                                data-url="<?php echo esc_attr( $cert['verify_url'] ); ?>"
                                title="<?php esc_attr_e( 'Copiar enlace de verificación', 'aura-suite' ); ?>">
                            🔗 <?php esc_html_e( 'Compartir', 'aura-suite' ); ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.aura-copy-verify-url').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var url = btn.dataset.url;
        if (navigator.clipboard && url) {
            navigator.clipboard.writeText(url).then(function() {
                btn.textContent = '✅';
                setTimeout(function() { btn.textContent = '🔗 <?php esc_js( __( 'Compartir', 'aura-suite' ) ); ?>'; }, 2000);
            });
        }
    });
});
</script>
