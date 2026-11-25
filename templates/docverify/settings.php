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

if ( ! current_user_can( 'aura_docverify_settings' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No tienes permisos para configurar Validación Documental.', 'aura-suite' ), 403 );
}

$settings    = Aura_DocVerify_Settings::get_all();
$active_tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? 'institutional' ) );
if ( ! in_array( $active_tab, [ 'institutional', 'folios', 'storage_pdf', 'security' ], true ) ) {
    $active_tab = 'institutional';
}
$drive_ready = Aura_DocVerify_Manager::is_drive_available();
$pages       = get_pages( [ 'post_status' => [ 'publish', 'draft' ] ] ) ?: [];
$dv_tip      = static function ( string $text ): string {
    return '<span class="aura-help-icon aura-tooltip-wrap" tabindex="0" data-tooltip="' . esc_attr( $text ) . '">?</span>';
};
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
                        <label class="aura-dv-label" for="dv-org-name"><?php esc_html_e( 'Nombre Oficial de la Institución', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Razón social o nombre institucional mostrado en la Hoja Anexa del PDF y en el portal de verificación.', 'aura-suite' ) ); ?></label>
                        <input type="text" id="dv-org-name" name="org_name" class="form-control" value="<?php echo esc_attr( $settings['org_name'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label" for="dv-org-tax-id"><?php esc_html_e( 'NIT / Registro Legal / Tax ID', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Identificación tributaria o personería jurídica de la entidad emisora, muy útil ante consulados y notarías.', 'aura-suite' ) ); ?></label>
                        <input type="text" id="dv-org-tax-id" name="org_tax_id" class="form-control" value="<?php echo esc_attr( $settings['org_tax_id'] ); ?>">
                    </div>
                </div>

                <div class="aura-dv-grid-2" style="margin-top:14px;">
                    <div>
                        <label class="aura-dv-label" for="dv-org-address"><?php esc_html_e( 'Dirección Física Oficial', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Sede principal física de la organización emisora.', 'aura-suite' ) ); ?></label>
                        <input type="text" id="dv-org-address" name="org_address" class="form-control" value="<?php echo esc_attr( $settings['org_address'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label" for="dv-org-country"><?php esc_html_e( 'Ciudad y País', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Ciudad y país de domicilio legal de la institución.', 'aura-suite' ) ); ?></label>
                        <input type="text" id="dv-org-country" name="org_country" class="form-control" value="<?php echo esc_attr( $settings['org_country'] ); ?>"
                               placeholder="<?php esc_attr_e( 'Ej: Bogotá, Colombia', 'aura-suite' ); ?>">
                    </div>
                </div>

                <div class="aura-dv-grid-2" style="margin-top:14px;">
                    <div>
                        <label class="aura-dv-label" for="dv-consular-email"><?php esc_html_e( 'Correo Oficial de Confirmación Consular', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Correo institucional que aparece como canal directo de confirmación para embajadas o autoridades migratorias.', 'aura-suite' ) ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">✉️</span>
                            <input type="email" id="dv-consular-email" name="consular_email" class="form-control" value="<?php echo esc_attr( $settings['consular_email'] ); ?>">
                        </div>
                    </div>
                    <div>
                        <label class="aura-dv-label" for="dv-consular-phone"><?php esc_html_e( 'Teléfono de Atención Institucional / Consular', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Teléfono de contacto directo para verificación telefónica por parte de entidades receptoras.', 'aura-suite' ) ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">📱</span>
                            <input type="text" id="dv-consular-phone" name="consular_phone" class="form-control" value="<?php echo esc_attr( $settings['consular_phone'] ); ?>">
                        </div>
                    </div>
                </div>

                <div style="margin-top:14px;">
                    <label class="aura-dv-label" for="dv-org-logo"><?php esc_html_e( 'URL del Logo Institucional', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Logotipo impreso en el encabezado de la Hoja Anexa del PDF y en la tarjeta pública web.', 'aura-suite' ) ); ?></label>
                    <div class="input-group">
                        <span class="input-group-text">🖼️</span>
                        <input type="url" id="dv-org-logo" name="org_logo_url" class="form-control" value="<?php echo esc_attr( $settings['org_logo_url'] ); ?>">
                        <button type="button" id="aura-dv-pick-logo" class="btn btn-secondary"><?php esc_html_e( 'Elegir de Medios', 'aura-suite' ); ?></button>
                    </div>
                </div>

            <?php elseif ( $active_tab === 'folios' ) :
                $curr_page_id    = (int) ( $settings['verify_page_id'] ?? 0 );
                $has_active_page = ( $curr_page_id > 0 && get_post_status( $curr_page_id ) === 'publish' );
                $route_mode      = ( (string) ( $settings['url_mode'] ?? 'clean_slug' ) === 'wp_page' && $has_active_page ) ? 'wp_page' : 'clean_slug';
                $page_permalink  = $has_active_page ? trailingslashit( (string) get_permalink( $curr_page_id ) ) : trailingslashit( home_url( $settings['verify_slug'] ) );
            ?>
                <h3><?php esc_html_e( 'Prefijos de Folios y Ruta Pública', 'aura-suite' ); ?></h3>
                <div class="aura-dv-grid-3" style="margin-top:12px;">
                    <div>
                        <label class="aura-dv-label" for="dv-route-mode"><?php esc_html_e( 'Modo de URL Pública en QR', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Elige si los códigos QR usarán la URL limpia /{slug}/{folio}?t=... o el enlace de la Página WordPress con shortcode (?doc={folio}&t=...).', 'aura-suite' ) ); ?></label>
                        <select id="dv-route-mode" name="route_mode" class="form-select form-control"
                                data-home-url="<?php echo esc_attr( rtrim( home_url(), '/' ) ); ?>"
                                data-page-url="<?php echo esc_attr( $page_permalink ); ?>">
                            <option value="clean_slug" <?php selected( $route_mode, 'clean_slug' ); ?>>
                                <?php esc_html_e( 'Ruta Limpia (/slug/FOLIO?t=...)', 'aura-suite' ); ?>
                            </option>
                            <option value="wp_page" <?php selected( $route_mode, 'wp_page' ); ?>>
                                <?php esc_html_e( 'Página WP Asignada (?doc=FOLIO&t=...)', 'aura-suite' ); ?>
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="aura-dv-label" for="dv-verify-slug"><?php esc_html_e( 'Slug Amigable de Verificación', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Segmento de URL usado en los QR cuando está activo el modo Ruta Limpia (ej: /verificar-documento/MIG-2026-0001).', 'aura-suite' ) ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><?php echo esc_html( rtrim( home_url(), '/' ) ); ?>/</span>
                            <input type="text" id="dv-verify-slug" name="verify_slug" class="form-control" value="<?php echo esc_attr( $settings['verify_slug'] ); ?>">
                        </div>
                    </div>
                    <div>
                        <label class="aura-dv-label" for="dv-folio-padding"><?php esc_html_e( 'Dígitos de Secuencia (Padding)', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Cantidad de ceros a la izquierda en el consecutivo del folio (4 = 0001, 5 = 00001).', 'aura-suite' ) ); ?></label>
                        <input type="number" id="dv-folio-padding" name="folio_padding" class="form-control" min="3" max="8" value="<?php echo esc_attr( (string) $settings['folio_padding'] ); ?>">
                    </div>
                </div>

                <div class="alert-card alert-info" style="margin-top:12px;">
                    <span class="alert-icon">🔗</span>
                    <div>
                        <div class="alert-title"><?php esc_html_e( 'Formato de URL resultante para los códigos QR:', 'aura-suite' ); ?></div>
                        <p style="margin:4px 0 0;">
                            <code id="aura-dv-url-preview"><?php
                                echo esc_html(
                                    $route_mode === 'wp_page'
                                        ? add_query_arg( [ 'doc' => 'INV-2026-0001', 't' => 'abc123...' ], $page_permalink )
                                        : rtrim( home_url(), '/' ) . '/' . $settings['verify_slug'] . '/INV-2026-0001?t=abc123...'
                                );
                            ?></code>
                        </p>
                    </div>
                </div>

                <div class="aura-dv-grid-3" style="margin-top:16px;">
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Carta de Invitación (Visa)', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Prefijo del folio cuando se selecciona Carta de Invitación para embajada/visa (por defecto INV).', 'aura-suite' ) ); ?></label>
                        <input type="text" name="prefix_invitation" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_invitation'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Carta Migración / Frontera', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Prefijo del folio para cartas presentadas en migración al entrar a un país sin visa (por defecto MIG).', 'aura-suite' ) ); ?></label>
                        <input type="text" name="prefix_migration" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_migration'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Oficio / Comunicado', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Prefijo para oficios y comunicados oficiales (por defecto OFI).', 'aura-suite' ) ); ?></label>
                        <input type="text" name="prefix_official" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_official'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Constancia', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Prefijo para constancias institucionales o laborales (por defecto CON).', 'aura-suite' ) ); ?></label>
                        <input type="text" name="prefix_constancy" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_constancy'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Carta Patrocinio', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Prefijo para cartas de patrocinio o respaldo económico (por defecto PAT).', 'aura-suite' ) ); ?></label>
                        <input type="text" name="prefix_sponsorship" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_sponsorship'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Autorización', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Prefijo para permisos o autorizaciones oficiales (por defecto AUT).', 'aura-suite' ) ); ?></label>
                        <input type="text" name="prefix_authorization" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_authorization'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Prefijo Otros Documentos', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Prefijo genérico para otros documentos institucionales (por defecto DOC).', 'aura-suite' ) ); ?></label>
                        <input type="text" name="prefix_other" class="form-control" maxlength="8" value="<?php echo esc_attr( $settings['prefix_other'] ); ?>">
                    </div>
                </div>

                <div class="alert-card alert-warning" style="margin-top:18px;">
                    <span class="alert-icon">🔄</span>
                    <div style="flex:1;">
                        <div class="alert-title"><?php esc_html_e( 'Actualizar URLs y QR de documentos ya creados', 'aura-suite' ); ?></div>
                        <p style="margin:4px 0 10px;">
                            <?php esc_html_e( 'Los prefijos aplican solo a documentos nuevos para no invalidar folios emitidos. Si cambiaste el Slug Amigable, el Modo de URL o el dominio, usa este botón para recalcular las URLs guardadas y regenerar las imágenes QR de todos los documentos existentes.', 'aura-suite' ); ?>
                        </p>
                        <label class="aura-dv-check-row" style="margin-bottom:10px;">
                            <input type="checkbox" id="aura-dv-sync-restamp" value="1">
                            <span><?php esc_html_e( 'Re-estampar también los PDFs certificados (Hoja Anexa / Sello QR) que conserven su original en Drive o local', 'aura-suite' ); ?></span>
                        </label>
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <button type="button" id="aura-dv-sync-urls-btn" class="btn btn-secondary btn-lift">
                                <span class="dashicons dashicons-update"></span>
                                <span><?php esc_html_e( 'Guardar y Actualizar URLs y QR existentes', 'aura-suite' ); ?></span>
                            </button>
                            <span id="aura-dv-sync-urls-result" class="aura-dv-subtext" style="font-weight:600;"></span>
                        </div>
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
                            <span><strong><?php esc_html_e( 'Guardar PDFs originales y sellados en Google Drive de AURA', 'aura-suite' ); ?></strong> <?php echo $dv_tip( __( 'Utiliza la conexión Google Drive de AURA para subir los PDFs originales y sellados de forma privada en carpetas Año/Mes.', 'aura-suite' ) ); ?></span>
                        </label>
                        <label class="aura-dv-check-row" style="margin-top:10px;">
                            <input type="checkbox" name="keep_local_backup" value="1" <?php checked( ! empty( $settings['keep_local_backup'] ) ); ?>>
                            <span><?php esc_html_e( 'Conservar también una copia local en el servidor tras subir a Drive', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Si lo activas, mantiene también copia en uploads/aura-docverify/pdf/ (protegida con .htaccess) además de Google Drive.', 'aura-suite' ) ); ?></span>
                        </label>
                    </div>
                    <div>
                        <label class="aura-dv-label" for="dv-drive-folder"><?php esc_html_e( 'Nombre de Subcarpeta en Google Drive', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Carpeta raíz del módulo dentro de la carpeta configurada en AURA Google Drive.', 'aura-suite' ) ); ?></label>
                        <input type="text" id="dv-drive-folder" name="drive_folder_name" class="form-control" value="<?php echo esc_attr( $settings['drive_folder_name'] ); ?>">
                    </div>
                </div>

                <div class="aura-dv-grid-3" style="margin-top:16px;">
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Modo de Sellado por Defecto', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Preselección al abrir Registrar PDF. Hoja Anexa Oficial ES/EN es la opción más segura si el PDF ya tiene firma electrónica.', 'aura-suite' ) ); ?></label>
                        <select name="default_stamp_mode" class="form-select form-control">
                            <option value="annex_page" <?php selected( $settings['default_stamp_mode'], 'annex_page' ); ?>><?php esc_html_e( 'Hoja Anexa Oficial ES/EN', 'aura-suite' ); ?></option>
                            <option value="overlay_corner" <?php selected( $settings['default_stamp_mode'], 'overlay_corner' ); ?>><?php esc_html_e( 'Sello QR en Esquina/Pie', 'aura-suite' ); ?></option>
                            <option value="none" <?php selected( $settings['default_stamp_mode'], 'none' ); ?>><?php esc_html_e( 'Solo generar QR', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Posición Sello Esquina', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Posición predeterminada cuando se elige estampar el sello sobre la última página del PDF.', 'aura-suite' ) ); ?></label>
                        <select name="default_stamp_pos" class="form-select form-control">
                            <option value="bottom_right" <?php selected( $settings['default_stamp_pos'], 'bottom_right' ); ?>><?php esc_html_e( 'Inferior derecha', 'aura-suite' ); ?></option>
                            <option value="bottom_left" <?php selected( $settings['default_stamp_pos'], 'bottom_left' ); ?>><?php esc_html_e( 'Inferior izquierda', 'aura-suite' ); ?></option>
                            <option value="bottom_bar" <?php selected( $settings['default_stamp_pos'], 'bottom_bar' ); ?>><?php esc_html_e( 'Franja inferior', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Tamaño máximo PDF (MB)', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Límite máximo permitido por archivo PDF subido (entre 2 y 50 MB).', 'aura-suite' ) ); ?></label>
                        <input type="number" name="max_upload_mb" class="form-control" min="2" max="50" value="<?php echo esc_attr( (string) $settings['max_upload_mb'] ); ?>">
                    </div>
                </div>

                <div class="aura-dv-grid-2" style="margin-top:14px;">
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Densidad y Lectura del Código QR (ECC)', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Estándar M (15%) genera códigos QR menos densos, con cuadros blancos y negros más grandes y fáciles de escanear en papel o móvil. Alta H (30%) tolera más daño físico pero genera cuadros más pequeños.', 'aura-suite' ) ); ?></label>
                        <select name="qr_ecc" class="form-select form-control">
                            <option value="M" <?php selected( strtoupper( (string) ( $settings['qr_ecc'] ?? 'M' ) ), 'M' ); ?>>
                                <?php esc_html_e( 'Estándar M (15%) — Cuadros más grandes y lectura rápida (Recomendado)', 'aura-suite' ); ?>
                            </option>
                            <option value="H" <?php selected( strtoupper( (string) ( $settings['qr_ecc'] ?? 'M' ) ), 'H' ); ?>>
                                <?php esc_html_e( 'Alta H (30%) — Máxima redundancia (más cuadros)', 'aura-suite' ); ?>
                            </option>
                        </select>
                    </div>
                </div>

            <?php else : ?>
                <h3><?php esc_html_e( 'Seguridad Anti-Enumeración y Página de Verificación', 'aura-suite' ); ?></h3>
                <div class="aura-dv-grid-3" style="margin-top:14px;">
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Página WP del Shortcode [aura_verificar_documento]', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Página pública de WordPress vinculada al verificador; también puedes gestionarla desde Configuración → Páginas.', 'aura-suite' ) ); ?></label>
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
                        <label class="aura-dv-label"><?php esc_html_e( 'Máximo de búsquedas por IP', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Número máximo de consultas o intentos de PIN permitidos desde una misma IP dentro de la ventana de tiempo.', 'aura-suite' ) ); ?></label>
                        <input type="number" name="rate_limit_max" class="form-control" min="5" max="120" value="<?php echo esc_attr( (string) $settings['rate_limit_max'] ); ?>">
                    </div>
                    <div>
                        <label class="aura-dv-label"><?php esc_html_e( 'Ventana de bloqueo (minutos)', 'aura-suite' ); ?> <?php echo $dv_tip( __( 'Duración en minutos de la ventana para el límite anti-fuerza bruta.', 'aura-suite' ) ); ?></label>
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
