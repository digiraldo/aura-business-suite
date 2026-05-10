<?php
/**
 * Template: Configuración del Módulo de Certificados
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$settings    = Aura_Certificates_Settings::get_all();
$active_tab  = sanitize_key( $_GET['tab'] ?? 'general' );
$tabs = [
    'general' => __( 'General', 'aura-suite' ),
    'emision' => __( 'Emisión', 'aura-suite' ),
    'paginas' => __( 'Páginas', 'aura-suite' ),
];

$pages = get_pages( [ 'post_status' => 'publish' ] );
?>
<div class="wrap aura-certificates-wrap">
    <h1 class="wp-heading-inline">⚙️ <?php esc_html_e( 'Configuración — Certificados', 'aura-suite' ); ?></h1>
    <hr class="wp-header-end">

    <nav class="nav-tab-wrapper" style="margin-bottom:16px;">
        <?php foreach ( $tabs as $slug => $label ) : ?>
        <a href="#"
           class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>"
           data-tab="<?php echo esc_attr( $slug ); ?>">
            <?php echo esc_html( $label ); ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div id="aura-cert-settings-msg"></div>

    <form id="aura-cert-settings-form" style="max-width:640px;">
        <?php wp_nonce_field( 'aura_certificates_nonce', 'nonce' ); ?>
        <input type="hidden" name="tab" id="aura-cert-active-tab" value="<?php echo esc_attr( $active_tab ); ?>">
        <div class="aura-ajustes-notices" style="margin-bottom:8px;"></div>

        <!-- TAB: GENERAL -->
        <div class="aura-tab-content" id="aura-tab-general" <?php echo $active_tab !== 'general' ? 'style="display:none;"' : ''; ?>>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Nombre de la Organización', 'aura-suite' ); ?></th>
                    <td>
                        <input type="text" name="org_name" class="regular-text"
                               value="<?php echo esc_attr( $settings['org_name'] ?? get_option( 'blogname', '' ) ); ?>">
                        <p class="description"><?php esc_html_e( 'Aparece en los certificados y en la página de verificación.', 'aura-suite' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Logo de la Organización', 'aura-suite' ); ?></th>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <img id="aura-cert-logo-preview"
                                 src="<?php echo esc_url( $settings['org_logo_url'] ?? '' ); ?>"
                                 style="max-height:60px;max-width:200px;<?php echo empty( $settings['org_logo_url'] ) ? 'display:none;' : ''; ?>">
                            <input type="hidden" name="org_logo_url" id="aura-cert-org-logo"
                                   value="<?php echo esc_attr( $settings['org_logo_url'] ?? '' ); ?>">
                            <button type="button" id="aura-cert-logo-btn" class="button">
                                <?php esc_html_e( 'Seleccionar Logo', 'aura-suite' ); ?>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Prefijo de Folio', 'aura-suite' ); ?></th>
                    <td>
                        <input type="text" name="folio_prefix" class="small-text"
                               value="<?php echo esc_attr( $settings['folio_prefix'] ?? 'CEM' ); ?>" maxlength="10"
                               placeholder="CEM">
                        <p class="description"><?php esc_html_e( 'Prefijo para el folio. Ej: CEM genera CEM-2026-0001', 'aura-suite' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Ceros en Folio', 'aura-suite' ); ?></th>
                    <td>
                        <input type="number" name="folio_padding" class="small-text"
                               value="<?php echo esc_attr( $settings['folio_padding'] ?? 4 ); ?>" min="1" max="10">
                        <p class="description"><?php esc_html_e( 'Cantidad de dígitos del número secuencial. 4 = 0001, 0042.', 'aura-suite' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Máx. Firmantes Activos', 'aura-suite' ); ?></th>
                    <td>
                        <input type="number" name="max_active_signers" class="small-text"
                               value="<?php echo esc_attr( $settings['max_active_signers'] ?? 4 ); ?>" min="1" max="10">
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'DPI del PDF', 'aura-suite' ); ?></th>
                    <td>
                        <select name="pdf_dpi" class="regular-text">
                            <?php foreach ( [ 96, 150, 200, 300 ] as $dpi ) : ?>
                            <option value="<?php echo $dpi; ?>" <?php selected( (int)( $settings['pdf_dpi'] ?? 150 ), $dpi ); ?>>
                                <?php echo $dpi; ?> DPI <?php echo $dpi >= 200 ? '(alta calidad)' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( '150 DPI es adecuado para la mayoría de usos.', 'aura-suite' ); ?></p>
                    </td>
                </tr>
            </table>
        </div><!-- /#aura-tab-general -->

        <!-- TAB: EMISIÓN -->
        <div class="aura-tab-content" id="aura-tab-emision" <?php echo $active_tab !== 'emision' ? 'style="display:none;"' : ''; ?>>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Requerir Paz y Salvo', 'aura-suite' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="require_paz_salvo" value="1"
                                   <?php checked( $settings['require_paz_salvo'] ?? false ); ?>>
                            <?php esc_html_e( 'Solo emitir certificados a estudiantes con paz y salvo financiero.', 'aura-suite' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Enviar Email por Defecto', 'aura-suite' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="default_send_email" value="1"
                                   <?php checked( $settings['default_send_email'] ?? true ); ?>>
                            <?php esc_html_e( 'Marcar "enviar email" por defecto al emitir un certificado.', 'aura-suite' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Enviar WhatsApp por Defecto', 'aura-suite' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="default_send_whatsapp" value="1"
                                   <?php checked( $settings['default_send_whatsapp'] ?? false ); ?>>
                            <?php esc_html_e( 'Marcar "enviar WhatsApp" por defecto.', 'aura-suite' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Incluir Firmas por Defecto', 'aura-suite' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="default_include_signatures" value="1"
                                   <?php checked( $settings['default_include_signatures'] ?? true ); ?>>
                            <?php esc_html_e( 'Incluir las firmas activas en el PDF por defecto.', 'aura-suite' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div><!-- /#aura-tab-emision -->

        <!-- TAB: PÁGINAS -->
        <div class="aura-tab-content" id="aura-tab-paginas" <?php echo $active_tab !== 'paginas' ? 'style="display:none;"' : ''; ?>>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Slug de Verificación', 'aura-suite' ); ?></th>
                    <td>
                        <input type="text" name="verify_slug" id="aura-cert-verify-slug" class="regular-text"
                               value="<?php echo esc_attr( $settings['verify_slug'] ?? 'verificar-certificado' ); ?>">
                        <p class="description">
                            <?php
                            printf(
                                esc_html__( 'Los certificados se verifican en: %s', 'aura-suite' ),
                                '<code id="aura-cert-verify-url-preview">' . esc_html( trailingslashit( home_url() ) ) . esc_html( $settings['verify_slug'] ?? 'verificar-certificado' ) . '/CEM-2026-0001</code>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Página "Mis Certificados"', 'aura-suite' ); ?></th>
                    <td>
                        <select name="cert_page_id" class="regular-text">
                            <option value="0"><?php esc_html_e( '— Sin asignar —', 'aura-suite' ); ?></option>
                            <?php foreach ( $pages as $page ) : ?>
                            <option value="<?php echo esc_attr( $page->ID ); ?>"
                                    <?php selected( (int)( $settings['cert_page_id'] ?? 0 ), $page->ID ); ?>>
                                <?php echo esc_html( $page->post_title ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Página que contiene el shortcode [aura_mis_certificados].', 'aura-suite' ); ?></p>
                    </td>
                </tr>
            </table>
        </div><!-- /#aura-tab-paginas -->

        <p class="submit">
            <button type="submit" id="aura-cert-settings-save" class="button button-primary">
                <?php esc_html_e( 'Guardar Configuración', 'aura-suite' ); ?>
            </button>
        </p>
    </form>
</div>
