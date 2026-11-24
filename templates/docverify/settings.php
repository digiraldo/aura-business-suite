<?php
/**
 * Template: Configuración del Módulo de Validación Documental
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings    = Aura_DocVerify_Settings::get_all();
$active_tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? 'institutional' ) );
if ( ! in_array( $active_tab, [ 'institutional', 'folios', 'storage_pdf', 'security' ], true ) ) {
    $active_tab = 'institutional';
}
$drive_ready = Aura_DocVerify_Manager::is_drive_available();
$pages       = get_pages( [ 'post_status' => [ 'publish', 'draft' ] ] ) ?: [];
?>
<div class="aura-app-wrapper aura-docverify-wrap" id="aura-docverify-settings-page">
    <div class="wrap aura-app-context">

        <?php
        Aura_UI::render_page_header( [
            'title'    => __( 'Configuración — Validación Documental', 'aura-suite' ),
            'subtitle' => __( 'Personaliza datos consulares de la organización, prefijos de folios, sellado FPDI/QR y respaldo en Google Drive', 'aura-suite' ),
            'icon'     => 'dashicons-admin-generic',
            'actions'  => [
                [
                    'label' => __( 'Dashboard', 'aura-suite' ),
                    'url'   => admin_url( 'admin.php?page=aura-docverify' ),
                    'class' => 'btn btn-glass btn-sm btn-lift',
                    'icon'  => 'dashicons-dashboard',
                ],
                [
                    'label' => __( 'Ver Documentos', 'aura-suite' ),
                    'url'   => admin_url( 'admin.php?page=aura-docverify-list' ),
                    'class' => 'btn btn-secondary btn-sm btn-lift',
                    'icon'  => 'dashicons-media-document',
                ],
            ],
        ] );
        ?>

        <!-- Navbar de pestañas canónico -->
        <nav class="aura-navbar aura-nav aura-glass-card tabs-bar" aria-label="<?php esc_attr_e( 'Pestañas de configuración', 'aura-suite' ); ?>">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-docverify-settings&tab=institutional' ) ); ?>"
               class="tab-btn <?php echo $active_tab === 'institutional' ? 'active' : ''; ?>">
                🏛️ <?php esc_html_e( 'Institución y Contacto Consular', 'aura-suite' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-docverify-settings&tab=folios' ) ); ?>"
               class="tab-btn <?php echo $active_tab === 'folios' ? 'active' : ''; ?>">
                🏷️ <?php esc_html_e( 'Folios y URL Pública', 'aura-suite' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-docverify-settings&tab=storage_pdf' ) ); ?>"
               class="tab-btn <?php echo $active_tab === 'storage_pdf' ? 'active' : ''; ?>">
                ☁️ <?php esc_html_e( 'Google Drive y Sellado PDF', 'aura-suite' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-docverify-settings&tab=security' ) ); ?>"
               class="tab-btn <?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                🛡️ <?php esc_html_e( 'Seguridad y Página Pública', 'aura-suite' ); ?>
            </a>
        </nav>

        <form id="aura-docverify-settings-form" class="aura-glass-card aura-dv-section-card" style="margin-top:16px;">
            <input type="hidden" name="settings_tab" value="<?php echo esc_attr( $active_tab ); ?>">

            <?php if ( $active_tab === 'institutional' ) : ?>
                <h3><?php esc_html_e( 'Identidad Institucional para Embajadas y Hoja Anexa', 'aura-suite' ); ?></h3>
                <p class="aura-dv-subtext" style="margin-bottom:16px;">
                    <?php esc_html_e( 'Se hereda automáticamente de Configuración → Organización de AURA; puedes especificar aquí datos adicionales de verificación consular.', 'aura-suite' ); ?>
                </p>

                <div class="aura-dv-grid-2">
                    <div>
                        <label class="aura-dv-label" for="dv-org-name"><?php esc_html_e( 'Nombre Oficial de la Institución', 'aura-suite' ); ?></label>
                        <input type="text" id="dv-org-name" name="org_name" class="form-control" value="<?php echo esc_attr( $settings['org_name'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label" for="dv-org-tax-id"><?php esc_html_e( 'NIT / Registro Legal / Tax ID', 'aura-suite' ); ?></label>
                        <input type="text" id="dv-org-tax-id" name="org_tax_id" class="form-control" value="<?php echo esc_attr( $settings['org_tax_id'] ); ?>">
                    </div>
                </div>

                <div class="aura-dv-grid-2" style="margin-top:14px;">
                    <div>
                        <label class="aura-dv-label" for="dv-org-address"><?php esc_html_e( 'Dirección Física Oficial', 'aura-suite' ); ?></label>
                        <input type="text" id="dv-org-address" name="org_address" class="form-control" value="<?php echo esc_attr( $settings['org_address'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label" for="dv-org-country"><?php esc_html_e( 'Ciudad y País', 'aura-suite' ); ?></label>
                        <input type="text" id="dv-org-country" name="org_country" class="form-control" value="<?php echo esc_attr( $settings['org_country'] ); ?>"
                               placeholder="<?php esc_attr_e( 'Ej: Bogotá, Colombia', 'aura-suite' ); ?>">
                    </div>
                </div>

                <div class="aura-dv-grid-2" style="margin-top:14px;">
                    <div>
                        <label class="aura-dv-label" for="dv-consular-email"><?php esc_html_e( 'Correo Oficial de Confirmación Consular', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">✉️</span>
                            <input type="email" id="dv-consular-email" name="consular_email" class="form-control" value="<?php echo esc_attr( $settings['consular_email'] ); ?>">
                        </div>
                    </div>
                    <div>
                        <label class="aura-dv-label" for="dv-consular-phone"><?php esc_html_e( 'Teléfono de Atención Institucional / Consular', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">📱</span>
                            <input type="text" id="dv-consular-phone" name="consular_phone" class="form-control" value="<?php echo esc_attr( $settings['consular_phone'] ); ?>">
                        </div>
                    </div>
                </div>

                <div style="margin-top:14px;">
                    <label class="aura-dv-label" for="dv-org-logo"><?php esc_html_e( 'URL del Logo Institucional', 'aura-suite' ); ?></label>
                    <div class="input-group">
                        <span class="input-group-text">🖼️</span>
                        <input type="url" id="dv-org-logo" name="org_logo_url" class="form-control" value="<?php echo esc_attr( $settings['org_logo_url'] ); ?>">
                        <button type="button" id="aura-dv-pick-logo" class="btn btn-secondary"><?php esc_html_e( 'Elegir de Medios', 'aura-suite' ); ?></button>
                    </div>
                </div>

            <?php elseif ( $active_tab === 'folios' ) : ?>
                <h3><?php esc_html_e( 'Prefijos de Folios y Ruta Pública', 'aura-suite' ); ?></h3>
                <div class="aura-dv-grid-2" style="margin-top:12px;">
                    <div>
                        <label class="aura-dv-label" for="dv-verify-slug"><?php esc_html_e( 'Slug Amigable de Verificación', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><?php echo esc_html( rtrim( home_url(), '/' ) ); ?>/</span>
                            <input type="text" id="dv-verify-slug" name="verify_slug" class="form-control" value="<?php echo esc_attr( $settings['verify_slug'] ); ?>">
                        </div>
                    </div>
                    <div>
                        <label class="aura-dv-label" for="dv-folio-padding"><?php esc_html_e( 'Dígitos de Secuencia (Padding)', 'aura-suite' ); ?></label>
                        <input type="number" id="dv-folio-padding" name="folio_padding" class="form-control" min="3" max="8" value="<?php echo esc_attr( (string) $settings['folio_padding'] ); ?>">
                    </div>
                </div>

                <div class="aura-dv-grid-3" style="margin-top:16px;">
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Carta de Invitación (Visa)', 'aura-suite' ); ?></label>
                        <input type="text" name="prefix_invitation" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_invitation'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Carta Migración / Frontera', 'aura-suite' ); ?></label>
                        <input type="text" name="prefix_migration" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_migration'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Oficio / Comunicado', 'aura-suite' ); ?></label>
                        <input type="text" name="prefix_official" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_official'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Constancia', 'aura-suite' ); ?></label>
                        <input type="text" name="prefix_constancy" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_constancy'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Carta Patrocinio', 'aura-suite' ); ?></label>
                        <input type="text" name="prefix_sponsorship" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_sponsorship'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Autorización', 'aura-suite' ); ?></label>
                        <input type="text" name="prefix_authorization" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_authorization'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Otros Documentos', 'aura-suite' ); ?></label>
                        <input type="text" name="prefix_other" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_other'] ); ?>">
                    </div>
                </div>

            <?php elseif ( $active_tab === 'storage_pdf' ) : ?>
                <h3><?php esc_html_e( 'Almacenamiento en Google Drive y Sellado PDF', 'aura-suite' ); ?></h3>

                <div class="alert-card <?php echo $drive_ready ? 'alert-success' : 'alert-warning'; ?>" style="margin:12px 0 18px;">
                    <span class="alert-icon"><?php echo $drive_ready ? '☁️' : '⚠️'; ?></span>
                    <div>
                        <div class="alert-title">
                            <?php echo $drive_ready
                                ? esc_html__( 'Google Drive de AURA está activo y listo', 'aura-suite' )
                                : esc_html__( 'Google Drive de AURA no está configurado aún', 'aura-suite' ); ?>
                        </div>
                        <p style="margin:4px 0 0;">
                            <?php esc_html_e( 'Este módulo comparte las credenciales Service Account de Configuración → Google Drive y almacena cada PDF de forma privada (sin enlace público abierto) dentro de la subcarpeta dedicada.', 'aura-suite' ); ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-settings&tab=gdrive' ) ); ?>"><?php esc_html_e( 'Ir a Configuración de Google Drive', 'aura-suite' ); ?></a>
                        </p>
                    </div>
                </div>

                <div class="aura-dv-grid-2">
                    <div>
                        <label class="aura-dv-check-row">
                            <input type="checkbox" name="use_google_drive" value="1" <?php checked( ! empty( $settings['use_google_drive'] ) ); ?>>
                            <span><strong><?php esc_html_e( 'Guardar PDFs originales y sellados en Google Drive de AURA', 'aura-suite' ); ?></strong></span>
                        </label>
                        <label class="aura-dv-check-row" style="margin-top:10px;">
                            <input type="checkbox" name="keep_local_backup" value="1" <?php checked( ! empty( $settings['keep_local_backup'] ) ); ?>>
                            <span><?php esc_html_e( 'Conservar también una copia local en el servidor tras subir a Drive', 'aura-suite' ); ?></span>
                        </label>
                    </div>
                    <div>
                        <label class="aura-dv-label" for="dv-drive-folder"><?php esc_html_e( 'Nombre de Subcarpeta en Google Drive', 'aura-suite' ); ?></label>
                        <input type="text" id="dv-drive-folder" name="drive_folder_name" class="form-control" value="<?php echo esc_attr( $settings['drive_folder_name'] ); ?>">
                    </div>
                </div>

                <div class="aura-dv-grid-3" style="margin-top:16px;">
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Modo de Sellado por Defecto', 'aura-suite' ); ?></label>
                        <select name="default_stamp_mode" class="form-select form-control">
                            <option value="annex_page" <?php selected( $settings['default_stamp_mode'], 'annex_page' ); ?>><?php esc_html_e( 'Hoja Anexa Oficial ES/EN', 'aura-suite' ); ?></option>
                            <option value="overlay_corner" <?php selected( $settings['default_stamp_mode'], 'overlay_corner' ); ?>><?php esc_html_e( 'Sello QR en Esquina/Pie', 'aura-suite' ); ?></option>
                            <option value="none" <?php selected( $settings['default_stamp_mode'], 'none' ); ?>><?php esc_html_e( 'Solo generar QR', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Posición Sello Esquina', 'aura-suite' ); ?></label>
                        <select name="default_stamp_pos" class="form-select form-control">
                            <option value="bottom_right" <?php selected( $settings['default_stamp_pos'], 'bottom_right' ); ?>><?php esc_html_e( 'Inferior derecha', 'aura-suite' ); ?></option>
                            <option value="bottom_left" <?php selected( $settings['default_stamp_pos'], 'bottom_left' ); ?>><?php esc_html_e( 'Inferior izquierda', 'aura-suite' ); ?></option>
                            <option value="bottom_bar" <?php selected( $settings['default_stamp_pos'], 'bottom_bar' ); ?>><?php esc_html_e( 'Franja inferior', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Tamaño máximo PDF (MB)', 'aura-suite' ); ?></label>
                        <input type="number" name="max_upload_mb" class="form-control" min="2" max="50" value="<?php echo esc_attr( (string) $settings['max_upload_mb'] ); ?>">
                    </div>
                </div>

            <?php else : ?>
                <h3><?php esc_html_e( 'Seguridad Anti-Enumeración y Página de Verificación', 'aura-suite' ); ?></h3>
                <div class="aura-dv-grid-3" style="margin-top:14px;">
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Página WP del Shortcode [aura_verificar_documento]', 'aura-suite' ); ?></label>
                        <select name="verify_page_id" class="form-select form-control">
                            <option value="0"><?php esc_html_e( '— Automático (Aura Page Manager) —', 'aura-suite' ); ?></option>
                            <?php foreach ( $pages as $p ) : ?>
                                <option value="<?php echo esc_attr( (string) $p->ID ); ?>" <?php selected( (int) $settings['verify_page_id'], (int) $p->ID ); ?>>
                                    <?php echo esc_html( $p->post_title . ' (/' . $p->post_name . '/)' ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Máximo de búsquedas por IP', 'aura-suite' ); ?></label>
                        <input type="number" name="rate_limit_max" class="form-control" min="5" max="120" value="<?php echo esc_attr( (string) $settings['rate_limit_max'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Ventana de bloqueo (minutos)', 'aura-suite' ); ?></label>
                        <input type="number" name="rate_limit_minutes" class="form-control" min="1" max="60" value="<?php echo esc_attr( (string) $settings['rate_limit_minutes'] ); ?>">
                    </div>
                </div>
            <?php endif; ?>

            <div style="margin-top:22px;display:flex;justify-content:flex-end;">
                <button type="submit" class="btn btn-primary btn-shimmer btn-lift">
                    <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Guardar Configuración', 'aura-suite' ); ?>
                </button>
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
