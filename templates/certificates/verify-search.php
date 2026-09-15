<?php
/**
 * Template: Formulario de búsqueda de certificado (para el shortcode sin folio)
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="aura-cert-verify-wrap">
    <div class="aura-cert-verify-search">
        <h2><?php esc_html_e( 'Verificar Certificado', 'aura-suite' ); ?></h2>
        <p><?php esc_html_e( 'Ingresa el folio del certificado para comprobar su autenticidad.', 'aura-suite' ); ?></p>
        <form method="GET" action="" style="display:flex;gap:8px;flex-wrap:wrap;">
            <input type="text" name="folio"
                   class="aura-input"
                   placeholder="<?php esc_attr_e( 'Ej: CEM-2026-0042', 'aura-suite' ); ?>"
                   required
                   style="flex:1;min-width:200px;">
            <button type="submit" class="button button-primary">
                <?php esc_html_e( 'Verificar', 'aura-suite' ); ?>
            </button>
        </form>
    </div>
</div>
