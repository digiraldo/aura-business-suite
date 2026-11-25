<?php
/**
 * Template Público: Buscador de Documentos Oficiales por Folio + Código de Verificación
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_standalone = ! isset( $verify_result );
$dv_locale     = class_exists( 'Aura_I18n' ) ? Aura_I18n::get_active_locale() : get_locale();
$dv_lang       = ( strpos( strtolower( (string) $dv_locale ), 'es' ) === 0 ) ? 'es' : 'en';
?>
<?php if ( $is_standalone ) : ?>
<div class="aura-dv-public-wrap" id="aura-dv-public-root" data-lang="<?php echo esc_attr( $dv_lang ); ?>">
    <div class="aura-dv-public-topbar no-print" style="justify-content:flex-end;">
        <div class="aura-dv-topbar-right">
            <?php
            if ( class_exists( 'Aura_Page_Manager' ) ) {
                echo Aura_Page_Manager::render_theme_toggle_bar( '', '0', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>
        </div>
    </div>
    <div class="aura-dv-card">
<?php endif; ?>

        <div class="aura-dv-search-box">
            <h2 data-es="🛡️ Verificar Autenticidad de Documento" data-en="🛡️ Verify Official Document Authenticity">🛡️ Verificar Autenticidad de Documento</h2>
            <p data-es="Ingresa el Folio Institucional y el Código de Verificación impresos en el documento o escanea el código QR."
               data-en="Enter the Official Folio and Verification Code printed on the document, or scan its QR code.">
                Ingresa el Folio Institucional y el Código de Verificación impresos en el documento o escanea el código QR.
            </p>
            <form method="GET" action="" class="aura-dv-search-form">
                <div>
                    <label data-es="Folio Institucional" data-en="Official Folio">Folio Institucional</label>
                    <input type="text" name="doc" required placeholder="Ej: INV-2026-0001">
                </div>
                <div>
                    <label data-es="Código de Verificación" data-en="Verification Code">Código de Verificación</label>
                    <input type="text" name="code" required placeholder="Ej: A8F3-92KD-7Q1M">
                </div>
                <button type="submit" class="aura-dv-btn-primary" data-es="Verificar Ahora" data-en="Verify Document">Verificar Ahora</button>
            </form>
        </div>

<?php if ( $is_standalone ) : ?>
    </div>
</div>
<?php endif; ?>
