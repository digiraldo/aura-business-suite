<?php
/**
 * Template: Registrar / Editar Documento Verificable (Subida PDF + Sello QR + Consular)
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$doc_id  = absint( $_GET['id'] ?? 0 );
$doc     = $doc_id > 0 ? Aura_DocVerify_Manager::get_document( $doc_id ) : null;
$is_edit = ( $doc !== null );

$doc_types   = Aura_DocVerify_Settings::get_document_types();
$signers     = Aura_DocVerify_Manager::get_available_signers();
$settings    = Aura_DocVerify_Settings::get_all();
$drive_ready = Aura_DocVerify_Manager::is_drive_available();

$val = static function ( string $field, $default = '' ) use ( $doc ) {
    if ( $doc && isset( $doc->$field ) ) {
        return $doc->$field;
    }
    return $default;
};
?>
<div class="aura-app-wrapper aura-docverify-wrap" id="aura-docverify-form-page">
    <div class="wrap aura-app-context">

        <?php
        Aura_UI::render_page_header( [
            'title'    => $is_edit
                ? sprintf( __( 'Editar Documento — %s', 'aura-suite' ), $doc->folio )
                : __( 'Registrar y Sellar Documento PDF', 'aura-suite' ),
            'subtitle' => __( 'Sube un PDF ya firmado (carta de invitación, oficio o constancia), genera su folio/QR y guárdalo de forma segura en Google Drive', 'aura-suite' ),
            'icon'     => 'dashicons-upload',
            'actions'  => [
                [
                    'label' => __( 'Volver al Listado', 'aura-suite' ),
                    'url'   => admin_url( 'admin.php?page=aura-docverify-list' ),
                    'class' => 'btn btn-glass btn-sm btn-lift',
                    'icon'  => 'dashicons-arrow-left-alt',
                ],
            ],
        ] );
        ?>

        <form id="aura-docverify-register-form" enctype="multipart/form-data" class="aura-dv-form-layout">
            <input type="hidden" name="id" value="<?php echo esc_attr( (string) ( $is_edit ? $doc->id : 0 ) ); ?>">

            <div class="aura-dv-form-main">
                <?php if ( ! $is_edit ) : ?>
                <!-- 1. Subida del PDF firmado -->
                <div class="aura-glass-card aura-dv-section-card">
                    <div class="aura-dv-section-head">
                        <h3>1. <?php esc_html_e( 'Archivo PDF Firmado', 'aura-suite' ); ?></h3>
                        <?php if ( $drive_ready ) : ?>
                            <span class="badge badge-cyan">☁️ <?php esc_html_e( 'Destino: Google Drive (Privado)', 'aura-suite' ); ?></span>
                        <?php else : ?>
                            <span class="badge badge-slate">🔒 <?php esc_html_e( 'Destino: Bóveda Local (.htaccess)', 'aura-suite' ); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="aura-bundle-dropzone aura-dv-dropzone" id="aura-dv-pdf-dropzone" tabindex="0">
                        <input type="file" id="aura-dv-pdf-input" name="signed_pdf" accept="application/pdf,.pdf" style="display:none;">
                        <div class="aura-dv-dropzone-empty" id="aura-dv-dz-empty">
                            <div class="aura-dv-dz-icon">📄</div>
                            <strong><?php esc_html_e( 'Arrastra aquí tu PDF firmado o haz clic para seleccionarlo', 'aura-suite' ); ?></strong>
                            <span>
                                <?php
                                printf(
                                    /* translators: %d = max MB */
                                    esc_html__( 'Solo formato PDF (%d MB máx.). Se calculará su huella criptográfica SHA-256.', 'aura-suite' ),
                                    (int) $settings['max_upload_mb']
                                );
                                ?>
                            </span>
                        </div>
                        <div class="aura-dv-dropzone-ready" id="aura-dv-dz-ready" style="display:none;">
                            <div class="aura-dv-dz-file-badge">✅ <strong id="aura-dv-dz-filename"></strong> <small id="aura-dv-dz-filesize"></small></div>
                            <div class="aura-dv-dz-sha"><span>SHA-256:</span> <code id="aura-dv-dz-sha256"><?php esc_html_e( 'Calculando…', 'aura-suite' ); ?></code></div>
                            <button type="button" class="btn btn-secondary btn-sm" id="aura-dv-dz-change"><?php esc_html_e( 'Cambiar archivo', 'aura-suite' ); ?></button>
                        </div>
                    </div>

                    <div class="aura-dv-grid-2" style="margin-top:16px;">
                        <div>
                            <label class="aura-dv-label" for="aura-dv-stamp-mode"><?php esc_html_e( 'Modo de Sellado / QR en PDF', 'aura-suite' ); ?></label>
                            <select id="aura-dv-stamp-mode" name="stamp_mode" class="form-select form-control">
                                <option value="annex_page" <?php selected( $settings['default_stamp_mode'], 'annex_page' ); ?>>
                                    <?php esc_html_e( 'Hoja Anexa Oficial ES/EN (Recomendado si el PDF tiene firma digital)', 'aura-suite' ); ?>
                                </option>
                                <option value="overlay_corner" <?php selected( $settings['default_stamp_mode'], 'overlay_corner' ); ?>>
                                    <?php esc_html_e( 'Estampar Sello QR compacto sobre la última página', 'aura-suite' ); ?>
                                </option>
                                <option value="none" <?php selected( $settings['default_stamp_mode'], 'none' ); ?>>
                                    <?php esc_html_e( 'No modificar el PDF (solo registrar SHA-256 y descargar QR PNG/SVG)', 'aura-suite' ); ?>
                                </option>
                            </select>
                        </div>
                        <div id="aura-dv-stamp-pos-wrap" style="<?php echo $settings['default_stamp_mode'] === 'overlay_corner' ? '' : 'display:none;'; ?>">
                            <label class="aura-dv-label" for="aura-dv-stamp-pos"><?php esc_html_e( 'Posición del Sello QR', 'aura-suite' ); ?></label>
                            <select id="aura-dv-stamp-pos" name="stamp_position" class="form-select form-control">
                                <option value="bottom_right" <?php selected( $settings['default_stamp_pos'], 'bottom_right' ); ?>><?php esc_html_e( 'Esquina inferior derecha', 'aura-suite' ); ?></option>
                                <option value="bottom_left" <?php selected( $settings['default_stamp_pos'], 'bottom_left' ); ?>><?php esc_html_e( 'Esquina inferior izquierda', 'aura-suite' ); ?></option>
                                <option value="bottom_bar" <?php selected( $settings['default_stamp_pos'], 'bottom_bar' ); ?>><?php esc_html_e( 'Franja horizontal inferior completa', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 2. Metadatos Institucionales y Consulares -->
                <div class="aura-glass-card aura-dv-section-card">
                    <div class="aura-dv-section-head">
                        <h3><?php echo $is_edit ? '1.' : '2.'; ?> <?php esc_html_e( 'Datos Institucionales y del Trámite', 'aura-suite' ); ?></h3>
                    </div>

                    <div class="aura-dv-grid-2">
                        <div>
                            <label class="aura-dv-label" for="aura-dv-doc-type"><?php esc_html_e( 'Tipo de Documento *', 'aura-suite' ); ?></label>
                            <div class="input-group">
                                <span class="input-group-text">📑</span>
                                <select id="aura-dv-doc-type" name="doc_type" class="form-select form-control">
                                    <?php foreach ( $doc_types as $tkey => $tlabel ) : ?>
                                        <option value="<?php echo esc_attr( $tkey ); ?>" <?php selected( $val( 'doc_type', 'invitation_letter' ), $tkey ); ?>><?php echo esc_html( $tlabel ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="aura-dv-label" for="aura-dv-doc-lang"><?php esc_html_e( 'Idioma del Documento', 'aura-suite' ); ?></label>
                            <div class="input-group">
                                <span class="input-group-text">🌐</span>
                                <select id="aura-dv-doc-lang" name="doc_language" class="form-select form-control">
                                    <option value="es" <?php selected( $val( 'doc_language', 'es' ), 'es' ); ?>><?php esc_html_e( 'Español', 'aura-suite' ); ?></option>
                                    <option value="en" <?php selected( $val( 'doc_language', 'es' ), 'en' ); ?>>English</option>
                                    <option value="bilingual" <?php selected( $val( 'doc_language', 'es' ), 'bilingual' ); ?>><?php esc_html_e( 'Bilingüe (ES / EN)', 'aura-suite' ); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:14px;">
                        <label class="aura-dv-label" for="aura-dv-title"><?php esc_html_e( 'Título / Asunto Oficial *', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">📝</span>
                            <input type="text" id="aura-dv-title" name="title_subject" class="form-control" required
                                   value="<?php echo esc_attr( (string) $val( 'title_subject' ) ); ?>"
                                   placeholder="<?php esc_attr_e( 'Ej: Carta de Invitación Institucional para Solicitud de Visa — Conferencia Anual 2026', 'aura-suite' ); ?>">
                        </div>
                    </div>

                    <div class="aura-dv-grid-2" style="margin-top:14px;">
                        <div>
                            <label class="aura-dv-label" for="aura-dv-recipient"><?php esc_html_e( 'Entidad Destinataria / Embajada / Consulado', 'aura-suite' ); ?></label>
                            <div class="input-group">
                                <span class="input-group-text">🏛️</span>
                                <input type="text" id="aura-dv-recipient" name="recipient_entity" class="form-control"
                                       value="<?php echo esc_attr( (string) $val( 'recipient_entity' ) ); ?>"
                                       placeholder="<?php esc_attr_e( 'Ej: Embajada de los Estados Unidos / Sección Consular', 'aura-suite' ); ?>">
                            </div>
                        </div>
                        <div>
                            <label class="aura-dv-label" for="aura-dv-country"><?php esc_html_e( 'País de Destino / Jurisdicción', 'aura-suite' ); ?></label>
                            <div class="input-group">
                                <span class="input-group-text">🌍</span>
                                <input type="text" id="aura-dv-country" name="recipient_country" class="form-control"
                                       value="<?php echo esc_attr( (string) $val( 'recipient_country' ) ); ?>"
                                       placeholder="<?php esc_attr_e( 'Ej: Estados Unidos / España / Canadá', 'aura-suite' ); ?>">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:14px;">
                        <label class="aura-dv-label" for="aura-dv-purpose"><?php esc_html_e( 'Propósito / Trámite', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">🎯</span>
                            <input type="text" id="aura-dv-purpose" name="purpose" class="form-control"
                                   value="<?php echo esc_attr( (string) $val( 'purpose' ) ); ?>"
                                   placeholder="<?php esc_attr_e( 'Ej: Trámite de Visa de Visitante B1/B2 — Participación en Seminario Internacional', 'aura-suite' ); ?>">
                        </div>
                    </div>
                </div>

                <!-- 3. Titular / Persona Invitada -->
                <div class="aura-glass-card aura-dv-section-card">
                    <div class="aura-dv-section-head">
                        <h3><?php echo $is_edit ? '2.' : '3.'; ?> <?php esc_html_e( 'Titular o Persona Invitada', 'aura-suite' ); ?></h3>
                    </div>

                    <div class="aura-dv-grid-3">
                        <div>
                            <label class="aura-dv-label" for="aura-dv-holder-name"><?php esc_html_e( 'Nombre Completo *', 'aura-suite' ); ?></label>
                            <div class="input-group">
                                <span class="input-group-text">👤</span>
                                <input type="text" id="aura-dv-holder-name" name="holder_name" class="form-control" required
                                       value="<?php echo esc_attr( (string) $val( 'holder_name' ) ); ?>"
                                       placeholder="<?php esc_attr_e( 'Nombre tal como figura en el pasaporte', 'aura-suite' ); ?>">
                            </div>
                        </div>
                        <div>
                            <label class="aura-dv-label" for="aura-dv-holder-doc-type"><?php esc_html_e( 'Tipo de Identificación', 'aura-suite' ); ?></label>
                            <select id="aura-dv-holder-doc-type" name="holder_doc_type" class="form-select form-control">
                                <option value="passport" <?php selected( $val( 'holder_doc_type', 'passport' ), 'passport' ); ?>><?php esc_html_e( 'Pasaporte', 'aura-suite' ); ?></option>
                                <option value="national_id" <?php selected( $val( 'holder_doc_type', 'passport' ), 'national_id' ); ?>><?php esc_html_e( 'Cédula / DNI / ID Nacional', 'aura-suite' ); ?></option>
                                <option value="tax_id" <?php selected( $val( 'holder_doc_type', 'passport' ), 'tax_id' ); ?>><?php esc_html_e( 'NIT / RUC / Tax ID', 'aura-suite' ); ?></option>
                                <option value="none" <?php selected( $val( 'holder_doc_type', 'passport' ), 'none' ); ?>><?php esc_html_e( 'No aplica', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                        <div>
                            <label class="aura-dv-label" for="aura-dv-holder-doc-num">
                                <?php esc_html_e( 'Número (se enmascara en público)', 'aura-suite' ); ?>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">🛂</span>
                                <input type="text" id="aura-dv-holder-doc-num" name="holder_doc_number" class="form-control"
                                       placeholder="<?php echo $is_edit && $doc->holder_doc_masked ? esc_attr( sprintf( __( 'Actual: %s (dejar vacío para conservar)', 'aura-suite' ), $doc->holder_doc_masked ) ) : esc_attr__( 'Ej: AB9874821', 'aura-suite' ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="aura-dv-grid-2" id="aura-dv-travel-dates" style="margin-top:14px;">
                        <div>
                            <label class="aura-dv-label" for="aura-dv-start"><?php esc_html_e( 'Inicio de Evento / Estadía (Opcional)', 'aura-suite' ); ?></label>
                            <div class="input-group">
                                <span class="input-group-text">📅</span>
                                <input type="date" id="aura-dv-start" name="travel_or_event_start" class="form-control"
                                       value="<?php echo esc_attr( (string) $val( 'travel_or_event_start' ) ); ?>">
                            </div>
                        </div>
                        <div>
                            <label class="aura-dv-label" for="aura-dv-end"><?php esc_html_e( 'Fin de Evento / Estadía (Opcional)', 'aura-suite' ); ?></label>
                            <div class="input-group">
                                <span class="input-group-text">📅</span>
                                <input type="date" id="aura-dv-end" name="travel_or_event_end" class="form-control"
                                       value="<?php echo esc_attr( (string) $val( 'travel_or_event_end' ) ); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna lateral de firmante, vigencia y privacidad -->
            <div class="aura-dv-form-side">
                <div class="aura-glass-card aura-dv-section-card">
                    <h3><?php esc_html_e( 'Firmante y Vigencia', 'aura-suite' ); ?></h3>

                    <?php if ( ! empty( $signers ) ) : ?>
                        <label class="aura-dv-label" for="aura-dv-signer-preset"><?php esc_html_e( 'Autocompletar Firmante Registrado', 'aura-suite' ); ?></label>
                        <select id="aura-dv-signer-preset" name="signer_id" class="form-select form-control" style="margin-bottom:12px;">
                            <option value=""><?php esc_html_e( '— Seleccionar o escribir manual —', 'aura-suite' ); ?></option>
                            <?php foreach ( $signers as $sig ) : ?>
                                <option value="<?php echo esc_attr( (string) $sig->id ); ?>"
                                        data-name="<?php echo esc_attr( $sig->name ); ?>"
                                        data-title="<?php echo esc_attr( $sig->title ); ?>"
                                        <?php selected( (int) $val( 'signer_id', 0 ), (int) $sig->id ); ?>>
                                    <?php echo esc_html( $sig->name . ( $sig->title ? ' — ' . $sig->title : '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <label class="aura-dv-label" for="aura-dv-signer-name"><?php esc_html_e( 'Nombre del Firmante', 'aura-suite' ); ?></label>
                    <input type="text" id="aura-dv-signer-name" name="signer_name" class="form-control" style="margin-bottom:10px;"
                           value="<?php echo esc_attr( (string) $val( 'signer_name' ) ); ?>"
                           placeholder="<?php esc_attr_e( 'Ej: Dr. Carlos Méndez', 'aura-suite' ); ?>">

                    <label class="aura-dv-label" for="aura-dv-signer-title"><?php esc_html_e( 'Cargo Institucional', 'aura-suite' ); ?></label>
                    <input type="text" id="aura-dv-signer-title" name="signer_title" class="form-control" style="margin-bottom:10px;"
                           value="<?php echo esc_attr( (string) $val( 'signer_title' ) ); ?>"
                           placeholder="<?php esc_attr_e( 'Ej: Director General / Representante Legal', 'aura-suite' ); ?>">

                    <label class="aura-dv-label" for="aura-dv-signer-email"><?php esc_html_e( 'Correo de Confirmación Consular', 'aura-suite' ); ?></label>
                    <input type="email" id="aura-dv-signer-email" name="signer_email" class="form-control" style="margin-bottom:14px;"
                           value="<?php echo esc_attr( (string) $val( 'signer_email', $settings['consular_email'] ) ); ?>">

                    <div class="aura-dv-grid-2">
                        <div>
                            <label class="aura-dv-label" for="aura-dv-issued-at"><?php esc_html_e( 'Fecha Emisión *', 'aura-suite' ); ?></label>
                            <input type="date" id="aura-dv-issued-at" name="issued_at" class="form-control" required
                                   value="<?php echo esc_attr( (string) $val( 'issued_at', current_time( 'Y-m-d' ) ) ); ?>">
                        </div>
                        <div>
                            <label class="aura-dv-label" for="aura-dv-expires-at"><?php esc_html_e( 'Válido Hasta', 'aura-suite' ); ?></label>
                            <input type="date" id="aura-dv-expires-at" name="expires_at" class="form-control"
                                   value="<?php echo esc_attr( (string) $val( 'expires_at' ) ); ?>">
                        </div>
                    </div>
                </div>

                <div class="aura-glass-card aura-dv-section-card">
                    <h3><?php esc_html_e( 'Privacidad y Control Consular', 'aura-suite' ); ?></h3>

                    <label class="aura-dv-label" for="aura-dv-privacy"><?php esc_html_e( 'Nivel de Visibilidad Pública', 'aura-suite' ); ?></label>
                    <select id="aura-dv-privacy" name="privacy_mode" class="form-select form-control" style="margin-bottom:12px;">
                        <option value="standard_masked" <?php selected( $val( 'privacy_mode', $settings['default_privacy_mode'] ), 'standard_masked' ); ?>>
                            <?php esc_html_e( 'Estándar Institucional (ID/Pasaporte enmascarado)', 'aura-suite' ); ?>
                        </option>
                        <option value="pin_protected" <?php selected( $val( 'privacy_mode', $settings['default_privacy_mode'] ), 'pin_protected' ); ?>>
                            <?php esc_html_e( 'Protegido con PIN o Pasaporte para ver detalle completo', 'aura-suite' ); ?>
                        </option>
                        <option value="public_summary" <?php selected( $val( 'privacy_mode', $settings['default_privacy_mode'] ), 'public_summary' ); ?>>
                            <?php esc_html_e( 'Resumen Público Completo', 'aura-suite' ); ?>
                        </option>
                    </select>

                    <label class="aura-dv-label" for="aura-dv-pin"><?php esc_html_e( 'PIN Opcional de Acceso Consular', 'aura-suite' ); ?></label>
                    <div class="input-group" style="margin-bottom:12px;">
                        <span class="input-group-text">🔒</span>
                        <input type="text" id="aura-dv-pin" name="access_pin" class="form-control"
                               placeholder="<?php echo $is_edit && ! empty( $doc->access_pin_hash ) ? esc_attr__( 'PIN configurado (escribir para cambiar)', 'aura-suite' ) : esc_attr__( 'Opcional (ej. código de cita)', 'aura-suite' ); ?>">
                    </div>

                    <label class="aura-dv-check-row">
                        <input type="checkbox" name="allow_public_pdf" value="1" <?php checked( (int) $val( 'allow_public_pdf', 0 ), 1 ); ?>>
                        <span><?php esc_html_e( 'Permitir descargar PDF sellado desde verificación web (si tiene PIN lo requerirá primero)', 'aura-suite' ); ?></span>
                    </label>

                    <label class="aura-dv-label" for="aura-dv-notes" style="margin-top:12px;"><?php esc_html_e( 'Notas Internas (Privadas)', 'aura-suite' ); ?></label>
                    <textarea id="aura-dv-notes" name="internal_notes" class="form-control" rows="2"><?php echo esc_textarea( (string) $val( 'internal_notes' ) ); ?></textarea>

                    <div id="aura-dv-submit-progress" class="loading-bar loading-bar-thick" style="display:none;margin-top:14px;"></div>

                    <button type="submit" id="aura-dv-submit-btn" class="btn btn-primary btn-shimmer btn-lift" style="width:100%;margin-top:14px;justify-content:center;">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php echo $is_edit ? esc_html__( 'Guardar Cambios', 'aura-suite' ) : esc_html__( 'Registrar, Generar QR y Subir a Nube', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>
        </form>

        <div class="adp-footer">
            <p>
                <?php esc_html_e( 'Desarrollado con', 'aura-suite' ); ?> ❤️ <?php esc_html_e( 'por', 'aura-suite' ); ?>
                <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link">DiGiraldo</a></strong>
                &nbsp;|&nbsp; © <?php echo esc_html( gmdate( 'Y' ) ); ?> AURA Business Suite
            </p>
        </div>
    </div>
</div>
