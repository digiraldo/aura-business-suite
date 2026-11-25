<?php
/**
 * Template Público: Tarjeta Institucional / Consular de Verificación Documental (ES / EN)
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 * @var array $verify_result Resultado de Aura_DocVerify_Verify::verify_lookup()
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$r      = $verify_result ?? [ 'found' => false, 'status' => 'not_found', 'folio' => '' ];
$found  = ! empty( $r['found'] );
$status = (string) ( $r['status'] ?? 'not_found' );
$org    = $r['org'] ?? [];

$dv_locale = class_exists( 'Aura_I18n' ) ? Aura_I18n::get_active_locale() : get_locale();
$dv_lang   = ( strpos( strtolower( (string) $dv_locale ), 'es' ) === 0 ) ? 'es' : 'en';

$public_pdf_url = '';
if ( $found && ! empty( $r['allow_public_pdf'] ) && empty( $r['is_locked'] ) ) {
    $public_pdf_url = add_query_arg( [
        'action' => 'aura_docverify_public_pdf',
        'folio'  => $r['folio'],
        'sig'    => $r['pdf_sig'],
    ], admin_url( 'admin-ajax.php' ) );
}
?>
<div class="aura-dv-public-wrap" id="aura-dv-public-root"
     data-lang="<?php echo esc_attr( $dv_lang ); ?>"
     data-folio="<?php echo esc_attr( (string) ( $r['folio'] ?? '' ) ); ?>"
     data-sig="<?php echo esc_attr( (string) ( $r['verify_sig'] ?? '' ) ); ?>"
     data-sha-orig="<?php echo esc_attr( (string) ( $r['sha256_original'] ?? '' ) ); ?>"
     data-sha-stamp="<?php echo esc_attr( (string) ( $r['sha256_stamped'] ?? '' ) ); ?>">

    <!-- Barra superior: Imprimir + Selector estándar de Idioma AURA + Modo Claro/Oscuro -->
    <div class="aura-dv-public-topbar no-print">
        <div class="aura-dv-topbar-left">
            <?php if ( $found ) : ?>
                <button type="button" class="aura-dv-print-btn" id="aura-dv-print-receipt" title="Print / Imprimir">
                    🖨️ <span data-es="Imprimir Comprobante" data-en="Print Verification"><?php echo esc_html( $dv_lang === 'en' ? 'Print Verification' : 'Imprimir Comprobante' ); ?></span>
                </button>
            <?php endif; ?>
        </div>
        <div class="aura-dv-topbar-right">
            <?php
            if ( class_exists( 'Aura_Page_Manager' ) ) {
                echo Aura_Page_Manager::render_theme_toggle_bar( '', '0', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>
        </div>
    </div>

    <?php if ( ! $found ) : ?>
        <div class="aura-dv-card aura-dv-card--invalid">
            <div class="aura-dv-status-hero is-danger">
                <div class="aura-dv-status-icon">❌</div>
                <div>
                    <?php if ( $status === 'rate_limited' ) : ?>
                        <h1 data-es="Límite de consultas alcanzado" data-en="Rate Limit Exceeded">Límite de consultas alcanzado</h1>
                        <p data-es="Por seguridad anti-enumeración, espera unos minutos antes de intentar de nuevo." data-en="For security reasons, please wait a few minutes before trying again.">Por seguridad anti-enumeración, espera unos minutos antes de intentar de nuevo.</p>
                    <?php elseif ( $status === 'invalid_token' ) : ?>
                        <h1 data-es="Código o Token de Verificación Inválido" data-en="Invalid Verification Code or Token">Código o Token de Verificación Inválido</h1>
                        <p data-es="El folio existe o fue consultado, pero el código de verificación o token del QR no coincide." data-en="The provided verification code or QR security token does not match our official records.">El folio existe o fue consultado, pero el código de verificación o token del QR no coincide.</p>
                    <?php else : ?>
                        <h1 data-es="Documento No Encontrado" data-en="Document Not Found">Documento No Encontrado</h1>
                        <p data-es="El folio consultado no figura en el registro oficial de nuestra institución." data-en="The queried folio does not appear in our official institutional registry.">El folio consultado no figura en el registro oficial de nuestra institución.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php include __DIR__ . '/verify-search.php'; ?>
        </div>
    <?php else : ?>
        <?php
        $card_mod = $status === 'active' ? 'is-valid glow-border-static' : ( $status === 'expired' ? 'is-expired' : 'is-revoked' );
        ?>
        <div class="aura-dv-card <?php echo esc_attr( $card_mod ); ?>">
            <!-- Cabecera institucional -->
            <div class="aura-dv-org-header">
                <div class="aura-dv-org-brand">
                    <?php if ( ! empty( $org['logo'] ) ) : ?>
                        <img src="<?php echo esc_url( $org['logo'] ); ?>" alt="<?php echo esc_attr( $org['name'] ); ?>" class="aura-dv-org-logo">
                    <?php endif; ?>
                    <div>
                        <div class="aura-dv-org-name"><?php echo esc_html( $org['name'] ); ?></div>
                        <div class="aura-dv-org-meta">
                            <?php
                            echo esc_html( implode( ' · ', array_filter( [
                                ! empty( $org['tax_id'] ) ? 'ID: ' . $org['tax_id'] : '',
                                $org['address'] ?? '',
                                $org['country'] ?? '',
                            ] ) ) );
                            ?>
                        </div>
                    </div>
                </div>
                <div class="aura-dv-folio-chip">
                    <span data-es="Folio Oficial" data-en="Official Folio">Folio Oficial</span>
                    <strong><?php echo esc_html( $r['folio'] ); ?></strong>
                </div>
            </div>

            <!-- Banner de estado oficial -->
            <?php if ( $status === 'active' ) : ?>
                <div class="aura-dv-status-hero is-valid">
                    <div class="aura-dv-status-icon">✅</div>
                    <div>
                        <span class="aura-dv-pill-badge is-valid">
                            <span class="aura-dv-dot"></span>
                            <span data-es="DOCUMENTO AUTÉNTICO Y VIGENTE" data-en="AUTHENTIC &amp; CURRENTLY VALID">DOCUMENTO AUTÉNTICO Y VIGENTE</span>
                        </span>
                        <h1 data-es="Verificación Institucional Exitosa" data-en="Official Institutional Verification">Verificación Institucional Exitosa</h1>
                        <p data-es="Este documento concuerda con nuestros registros oficiales emitidos y firmados." data-en="This record matches the official signed document issued by our institution.">Este documento concuerda con nuestros registros oficiales emitidos y firmados.</p>
                    </div>
                </div>
            <?php elseif ( $status === 'expired' ) : ?>
                <div class="aura-dv-status-hero is-warning">
                    <div class="aura-dv-status-icon">⏳</div>
                    <div>
                        <span class="aura-dv-pill-badge is-warning" data-es="DOCUMENTO EXPIRADO" data-en="EXPIRED DOCUMENT">DOCUMENTO EXPIRADO</span>
                        <h1 data-es="Documento Auténtico — Vigencia Vencida" data-en="Authentic Record — Validity Period Expired">Documento Auténtico — Vigencia Vencida</h1>
                        <p data-es="El documento existió en nuestros registros pero su fecha límite de vigencia expiró." data-en="The document is authentic in our registry, however its official validity period has expired.">El documento existió en nuestros registros pero su fecha límite de vigencia expiró.</p>
                    </div>
                </div>
            <?php else : ?>
                <div class="aura-dv-status-hero is-danger">
                    <div class="aura-dv-status-icon">🚫</div>
                    <div>
                        <span class="aura-dv-pill-badge is-danger" data-es="DOCUMENTO REVOCADO" data-en="REVOKED DOCUMENT">DOCUMENTO REVOCADO</span>
                        <h1 data-es="Documento Anulado / Sin Validez Oficial" data-en="Document Revoked / No Longer Valid">Documento Anulado / Sin Validez Oficial</h1>
                        <?php if ( ! empty( $r['revoke_reason'] ) ) : ?>
                            <p><strong data-es="Motivo oficial:" data-en="Official reason:">Motivo oficial:</strong> <?php echo esc_html( $r['revoke_reason'] ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Ficha de datos oficiales -->
            <div class="aura-dv-specs-grid">
                <div class="aura-dv-spec">
                    <small data-es="Tipo de Documento" data-en="Document Type">Tipo de Documento</small>
                    <strong data-es="<?php echo esc_attr( $r['doc_type_es'] ); ?>" data-en="<?php echo esc_attr( $r['doc_type_en'] ); ?>"><?php echo esc_html( $r['doc_type_es'] ); ?></strong>
                </div>
                <div class="aura-dv-spec">
                    <small data-es="Asunto / Título" data-en="Subject / Title">Asunto / Título</small>
                    <strong><?php echo esc_html( $r['title_subject'] ); ?></strong>
                </div>
                <div class="aura-dv-spec">
                    <small data-es="Titular / Invitado" data-en="Document Holder / Invitee">Titular / Invitado</small>
                    <strong>
                        <?php echo esc_html( $r['holder_name'] ); ?>
                        <?php if ( ! empty( $r['holder_doc_masked'] ) ) : ?>
                            <span class="aura-dv-muted"> (ID: <?php echo esc_html( $r['holder_doc_masked'] ); ?>)</span>
                        <?php endif; ?>
                    </strong>
                </div>
                <div class="aura-dv-spec">
                    <small data-es="Entidad Destinataria / Autoridad" data-en="Recipient Authority / Entity">Entidad Destinataria / Autoridad</small>
                    <strong id="aura-dv-pub-recipient">
                        <?php
                        if ( ! empty( $r['is_locked'] ) ) {
                            echo '<em data-es="Protegido — Validar PIN o Pasaporte" data-en="Protected — Verify PIN or Passport">' . esc_html__( 'Protegido — Validar PIN o Pasaporte', 'aura-suite' ) . '</em>';
                        } else {
                            echo esc_html( ( $r['recipient_entity'] ?: '—' ) . ( $r['recipient_country'] ? ' (' . $r['recipient_country'] . ')' : '' ) );
                        }
                        ?>
                    </strong>
                </div>
                <div class="aura-dv-spec">
                    <small data-es="Propósito / Trámite" data-en="Purpose / Procedure">Propósito / Trámite</small>
                    <strong id="aura-dv-pub-purpose">
                        <?php echo ! empty( $r['is_locked'] ) ? '••••••' : esc_html( $r['purpose'] ?: '—' ); ?>
                    </strong>
                </div>
                <div class="aura-dv-spec">
                    <small data-es="Firmante Autorizado" data-en="Authorized Signatory">Firmante Autorizado</small>
                    <strong>
                        <?php echo esc_html( $r['signer_name'] ?: '—' ); ?>
                        <?php if ( ! empty( $r['signer_title'] ) ) : ?>
                            <span class="aura-dv-muted"> — <?php echo esc_html( $r['signer_title'] ); ?></span>
                        <?php endif; ?>
                    </strong>
                </div>
                <div class="aura-dv-spec">
                    <small data-es="Fecha de Emisión" data-en="Issue Date">Fecha de Emisión</small>
                    <strong><?php echo esc_html( $r['issued_at'] ); ?></strong>
                </div>
                <div class="aura-dv-spec">
                    <small data-es="Válido Hasta" data-en="Valid Until">Válido Hasta</small>
                    <strong data-es="<?php echo esc_attr( $r['expires_at'] ?: 'Sin vencimiento' ); ?>"
                            data-en="<?php echo esc_attr( $r['expires_at'] ?: 'No expiration date' ); ?>">
                        <?php echo esc_html( $r['expires_at'] ?: 'Sin vencimiento' ); ?>
                    </strong>
                </div>
                <?php if ( ! empty( $r['travel_or_event_start'] ) || ! empty( $r['travel_or_event_end'] ) ) : ?>
                <div class="aura-dv-spec">
                    <small data-es="Período de Estadía / Evento" data-en="Stay / Event Period">Período de Estadía / Evento</small>
                    <strong id="aura-dv-pub-period">
                        <?php echo ! empty( $r['is_locked'] ) ? '••••••' : esc_html( trim( (string) ( $r['travel_or_event_start'] ?? '' ) . ' → ' . (string) ( $r['travel_or_event_end'] ?? '' ), ' →' ) ); ?>
                    </strong>
                </div>
                <?php endif; ?>
                <?php if ( ! empty( $r['stay_address'] ) ) : ?>
                <div class="aura-dv-spec">
                    <small data-es="Lugar de Estadía / Respaldo" data-en="Accommodation / Host Address">Lugar de Estadía / Respaldo</small>
                    <strong id="aura-dv-pub-stay">
                        <?php echo ! empty( $r['is_locked'] ) ? '••••••' : esc_html( $r['stay_address'] ); ?>
                    </strong>
                </div>
                <?php endif; ?>
            </div>

            <!-- Desbloqueo consular por PIN o Pasaporte si aplica -->
            <?php if ( ! empty( $r['has_pin_or_doc'] ) && ( ! empty( $r['is_locked'] ) || ! empty( $r['allow_public_pdf'] ) ) ) : ?>
                <div class="aura-dv-unlock-box no-print" id="aura-dv-unlock-box">
                    <h4 data-es="🔐 Verificación Adicional / Descarga Oficial (Embajada o Titular)" data-en="🔐 Additional Verification / Official Copy Download (Embassy or Holder)">
                        🔐 Verificación Adicional / Descarga Oficial (Embajada o Titular)
                    </h4>
                    <p data-es="Ingresa el PIN consular o el número completo de pasaporte/documento del titular para desbloquear el detalle completo o descargar la copia certificada."
                       data-en="Enter the consular PIN or full passport/ID number of the holder to unlock full details or download the certified copy.">
                        Ingresa el PIN consular o el número completo de pasaporte/documento del titular para desbloquear el detalle completo o descargar la copia certificada.
                    </p>
                    <form id="aura-dv-unlock-form" class="aura-dv-unlock-form">
                        <input type="password" id="aura-dv-unlock-secret" required
                               placeholder="PIN / Passport / ID Number">
                        <button type="submit" class="aura-dv-btn-primary" data-es="Validar y Desbloquear" data-en="Verify &amp; Unlock">Validar y Desbloquear</button>
                    </form>
                    <div id="aura-dv-unlock-msg" style="margin-top:8px;"></div>
                </div>
            <?php endif; ?>

            <div id="aura-dv-public-download-wrap" class="aura-dv-download-wrap no-print" style="<?php echo $public_pdf_url ? '' : 'display:none;'; ?>">
                <a id="aura-dv-public-download-link" href="<?php echo esc_url( $public_pdf_url ); ?>" class="aura-dv-btn-primary">
                    📥 <span data-es="Descargar PDF Oficial Certificado" data-en="Download Certified Official PDF">Descargar PDF Oficial Certificado</span>
                </a>
            </div>

            <!-- Verificador Criptográfico SHA-256 Zero-Upload -->
            <div class="aura-dv-sha-box">
                <div class="aura-dv-sha-header">
                    <h4 data-es="🛡️ Verificador Criptográfico de Integridad PDF (SHA-256)" data-en="🛡️ Cryptographic PDF Integrity Checker (SHA-256)">
                        🛡️ Verificador Criptográfico de Integridad PDF (SHA-256)
                    </h4>
                    <span class="aura-dv-sha-privacy" data-es="100% local en tu navegador — El archivo no se sube al servidor" data-en="100% local in browser — File is never uploaded">
                        100% local en tu navegador — El archivo no se sube al servidor
                    </span>
                </div>
                <div class="aura-dv-sha-digest">
                    <small data-es="Huella SHA-256 Original Registrada:" data-en="Registered Original SHA-256 Digest:">Huella SHA-256 Original Registrada:</small>
                    <code><?php echo esc_html( strtoupper( $r['sha256_original'] ) ); ?></code>
                </div>
                <?php if ( ! empty( $r['sha256_stamped'] ) ) : ?>
                    <div class="aura-dv-sha-digest" style="margin-top:6px;">
                        <small data-es="Huella SHA-256 PDF Sellado / Anexo:" data-en="Registered Stamped / Annex PDF SHA-256:">Huella SHA-256 PDF Sellado / Anexo:</small>
                        <code><?php echo esc_html( strtoupper( $r['sha256_stamped'] ) ); ?></code>
                    </div>
                <?php endif; ?>

                <div class="aura-dv-sha-drop no-print" id="aura-dv-public-sha-drop">
                    <input type="file" id="aura-dv-public-sha-input" accept="application/pdf,.pdf" style="display:none;">
                    <span data-es="📎 Haz clic o arrastra aquí el PDF que recibiste para comprobar si es bit a bit idéntico al original"
                          data-en="📎 Click or drag &amp; drop the PDF you received to verify it matches bit-for-bit with the official record">
                        📎 Haz clic o arrastra aquí el PDF que recibiste para comprobar si es bit a bit idéntico al original
                    </span>
                </div>
                <div id="aura-dv-public-sha-result" style="display:none;"></div>
            </div>

            <!-- Pie de contacto institucional consular -->
            <div class="aura-dv-public-footer">
                <div>
                    <strong data-es="Canal de Confirmación Consular / Institucional:" data-en="Official Institutional Confirmation Channel:">Canal de Confirmación Consular / Institucional:</strong>
                    <?php
                    echo esc_html( implode( ' · ', array_filter( [
                        $org['email'] ?? '',
                        $org['phone'] ?? '',
                    ] ) ) ?: '—' );
                    ?>
                </div>
                <div class="aura-dv-timestamp">
                    <span data-es="Verificado:" data-en="Verified:">Verificado:</span> <?php echo esc_html( $r['checked_at_utc'] ); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
