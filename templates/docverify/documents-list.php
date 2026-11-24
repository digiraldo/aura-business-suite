<?php
/**
 * Template: Listado Maestro de Documentos Verificables
 *
 * Cumple P.E.E. Design System:
 *  - Layout 100% Full-Width (.aura-app-wrapper + .wrap.aura-app-context)
 *  - Filtros horizontales 100% Bootstrap 5.3 (.input-group)
 *  - DataTable .aura-table.table-row-hover-lift con Child Rows +/− y responsive <=782px
 *  - Miniaturas QR con .aura-table-thumb-preview + .aura-floating-img-card
 *  - Modales Item 26 (Detalle/QR + Revocación) con Skeleton Loader + guardia 2 fases
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$search         = sanitize_text_field( wp_unslash( $_GET['s_doc'] ?? '' ) );
$filter_type    = sanitize_key( wp_unslash( $_GET['doc_type'] ?? '' ) );
$filter_status  = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
$filter_driver  = sanitize_key( wp_unslash( $_GET['storage_driver'] ?? '' ) );
$paged          = max( 1, absint( $_GET['paged'] ?? 1 ) );
$created_id     = absint( $_GET['created'] ?? 0 );

$listing = Aura_DocVerify_Manager::list_documents( [
    'search'         => $search,
    'doc_type'       => $filter_type,
    'status'         => $filter_status,
    'storage_driver' => $filter_driver,
    'page'           => $paged,
    'per_page'       => 20,
] );

$doc_types    = Aura_DocVerify_Settings::get_document_types();
$can_create   = current_user_can( 'aura_docverify_create' ) || current_user_can( 'manage_options' );
$can_edit     = current_user_can( 'aura_docverify_edit' ) || current_user_can( 'manage_options' );
$can_revoke   = current_user_can( 'aura_docverify_revoke' ) || current_user_can( 'manage_options' );
$can_delete   = current_user_can( 'aura_docverify_delete' ) || current_user_can( 'manage_options' );
$can_download = current_user_can( 'aura_docverify_download_pdf' ) || current_user_can( 'manage_options' );
$nonce        = wp_create_nonce( 'aura_docverify_nonce' );
?>
<div class="aura-app-wrapper aura-docverify-wrap" id="aura-docverify-list-page" data-created-id="<?php echo esc_attr( (string) $created_id ); ?>">
    <div class="wrap aura-app-context">

        <?php
        $header_actions = [
            [
                'label' => __( 'Dashboard', 'aura-suite' ),
                'url'   => admin_url( 'admin.php?page=aura-docverify' ),
                'class' => 'btn btn-glass btn-sm btn-lift',
                'icon'  => 'dashicons-dashboard',
            ],
        ];
        if ( $can_create ) {
            $header_actions[] = [
                'label' => __( 'Registrar PDF Firmado', 'aura-suite' ),
                'url'   => admin_url( 'admin.php?page=aura-docverify-register' ),
                'class' => 'btn btn-primary btn-shimmer btn-sm btn-lift',
                'icon'  => 'dashicons-plus-alt2',
            ];
        }

        Aura_UI::render_page_header( [
            'title'    => __( 'Documentos Verificables', 'aura-suite' ),
            'subtitle' => __( 'Administra folios institucionales, códigos QR, firmas, cartas de invitación y archivos respaldados en Google Drive', 'aura-suite' ),
            'icon'     => 'dashicons-shield',
            'actions'  => $header_actions,
        ] );
        ?>

        <!-- Barra de filtros horizontales 100% Full-Width -->
        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="aura-filters-card aura-glass-card">
            <input type="hidden" name="page" value="aura-docverify-list">
            <div class="aura-dv-filters-grid">
                <div class="input-group">
                    <span class="input-group-text">🔍</span>
                    <input type="text" name="s_doc" class="form-control"
                           value="<?php echo esc_attr( $search ); ?>"
                           placeholder="<?php esc_attr_e( 'Folio, código, asunto, invitado o embajada…', 'aura-suite' ); ?>">
                </div>

                <div class="input-group">
                    <span class="input-group-text">📄</span>
                    <select name="doc_type" class="form-select form-control">
                        <option value=""><?php esc_html_e( 'Todos los tipos', 'aura-suite' ); ?></option>
                        <?php foreach ( $doc_types as $tkey => $tlabel ) : ?>
                            <option value="<?php echo esc_attr( $tkey ); ?>" <?php selected( $filter_type, $tkey ); ?>><?php echo esc_html( $tlabel ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <span class="input-group-text">🚦</span>
                    <select name="status" class="form-select form-control">
                        <option value=""><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
                        <option value="active" <?php selected( $filter_status, 'active' ); ?>><?php esc_html_e( 'Vigentes', 'aura-suite' ); ?></option>
                        <option value="expired" <?php selected( $filter_status, 'expired' ); ?>><?php esc_html_e( 'Expirados', 'aura-suite' ); ?></option>
                        <option value="revoked" <?php selected( $filter_status, 'revoked' ); ?>><?php esc_html_e( 'Revocados', 'aura-suite' ); ?></option>
                    </select>
                </div>

                <div class="input-group">
                    <span class="input-group-text">☁️</span>
                    <select name="storage_driver" class="form-select form-control">
                        <option value=""><?php esc_html_e( 'Todo almacenamiento', 'aura-suite' ); ?></option>
                        <option value="google_drive" <?php selected( $filter_driver, 'google_drive' ); ?>>Google Drive</option>
                        <option value="local" <?php selected( $filter_driver, 'local' ); ?>><?php esc_html_e( 'Local Privado', 'aura-suite' ); ?></option>
                    </select>
                </div>

                <div class="aura-dv-filter-btns">
                    <button type="submit" class="btn btn-primary btn-shimmer">
                        <span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Filtrar', 'aura-suite' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-docverify-list' ) ); ?>" class="btn btn-secondary">
                        <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
                    </a>
                </div>
            </div>
        </form>

        <!-- Tabla maestra -->
        <div class="aura-glass-card">
            <?php if ( empty( $listing['items'] ) ) : ?>
                <div class="empty-card">
                    <div class="empty-icon">📂</div>
                    <h4><?php esc_html_e( 'No se encontraron documentos', 'aura-suite' ); ?></h4>
                    <p><?php esc_html_e( 'Ajusta los filtros o registra un nuevo PDF firmado.', 'aura-suite' ); ?></p>
                    <?php if ( $can_create ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-docverify-register' ) ); ?>" class="btn btn-primary btn-shimmer btn-lift">
                            <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Registrar PDF', 'aura-suite' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="aura-dt-wrapper aura-table-responsive-wrap">
                    <table class="aura-table table-row-hover-lift aura-dv-table">
                        <thead>
                            <tr>
                                <th style="width:42px;"></th>
                                <th style="width:54px;"><?php esc_html_e( 'QR', 'aura-suite' ); ?></th>
                                <th>
                                    <span class="aura-th-flex">
                                        <?php esc_html_e( 'Folio y Código', 'aura-suite' ); ?>
                                        <span class="aura-help-icon aura-tooltip-wrap" data-tooltip="<?php esc_attr_e( 'Folio único institucional y código aleatorio anti-enumeración.', 'aura-suite' ); ?>">?</span>
                                    </span>
                                </th>
                                <th><?php esc_html_e( 'Documento y Destinatario', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Titular / Invitado', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Emisión / Vigencia', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $listing['items'] as $doc ) :
                                $eff      = Aura_DocVerify_Manager::get_effective_status( $doc );
                                $qr_url   = add_query_arg( [ 'action' => 'aura_docverify_qr_preview', 'id' => $doc->id, 'nonce' => $nonce ], admin_url( 'admin-ajax.php' ) );
                                $type_lbl = $doc_types[ $doc->doc_type ] ?? $doc->doc_type;
                                $dl_stamp = add_query_arg( [ 'action' => 'aura_docverify_download_pdf', 'id' => $doc->id, 'variant' => 'stamped', 'nonce' => $nonce ], admin_url( 'admin-ajax.php' ) );
                                ?>
                                <tr class="aura-dv-row" data-id="<?php echo esc_attr( (string) $doc->id ); ?>">
                                    <td>
                                        <button type="button" class="aura-dv-row-toggle btn btn-secondary btn-sm"
                                                aria-expanded="false"
                                                data-id="<?php echo esc_attr( (string) $doc->id ); ?>"
                                                title="<?php esc_attr_e( 'Ver detalles criptográficos y hashes', 'aura-suite' ); ?>">+</button>
                                    </td>
                                    <td class="aura-table-logo-cell">
                                        <div class="aura-tooltip-wrap aura-table-thumb-wrap">
                                            <div class="aura-table-thumb-preview aura-img-preview-trigger aura-dv-open-detail" data-id="<?php echo esc_attr( (string) $doc->id ); ?>">
                                                <img src="<?php echo esc_url( $qr_url ); ?>" width="36" height="36" alt="QR <?php echo esc_attr( $doc->folio ); ?>">
                                            </div>
                                            <div class="aura-tooltip-content">
                                                <div class="aura-floating-img-card">
                                                    <img src="<?php echo esc_url( $qr_url ); ?>" alt="QR <?php echo esc_attr( $doc->folio ); ?>">
                                                    <span class="aura-floating-img-title"><?php echo esc_html( $doc->folio ); ?></span>
                                                    <span class="aura-floating-img-sub"><?php echo esc_html( $doc->verification_code ); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-blue"><?php echo esc_html( $doc->folio ); ?></span>
                                        <div class="aura-dv-code-line"><code><?php echo esc_html( $doc->verification_code ); ?></code></div>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $doc->title_subject ); ?></strong>
                                        <div class="aura-dv-subtext">
                                            <span class="badge badge-slate badge-sm"><?php echo esc_html( $type_lbl ); ?></span>
                                            <?php if ( ! empty( $doc->recipient_entity ) ) : ?>
                                                <span> · 🏛️ <?php echo esc_html( $doc->recipient_entity ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $doc->holder_name ); ?></strong>
                                        <?php if ( ! empty( $doc->holder_doc_masked ) ) : ?>
                                            <div class="aura-dv-subtext">🛂 <?php echo esc_html( $doc->holder_doc_masked ); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo esc_html( mysql2date( 'd/m/Y', $doc->issued_at ) ); ?></div>
                                        <div class="aura-dv-subtext">
                                            <?php
                                            echo ! empty( $doc->expires_at )
                                                ? esc_html__( 'Vence:', 'aura-suite' ) . ' ' . esc_html( mysql2date( 'd/m/Y', $doc->expires_at ) )
                                                : esc_html__( 'Sin vencimiento', 'aura-suite' );
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ( $eff === 'active' ) : ?>
                                            <span class="badge badge-traffic-success"><span class="traffic-dot traffic-dot-success"></span> <?php esc_html_e( 'Vigente', 'aura-suite' ); ?></span>
                                        <?php elseif ( $eff === 'expired' ) : ?>
                                            <span class="badge badge-traffic-warning"><span class="traffic-dot traffic-dot-warning"></span> <?php esc_html_e( 'Expirado', 'aura-suite' ); ?></span>
                                        <?php else : ?>
                                            <span class="badge badge-traffic-danger"><span class="traffic-dot traffic-dot-danger"></span> <?php esc_html_e( 'Revocado', 'aura-suite' ); ?></span>
                                        <?php endif; ?>
                                        <div class="aura-dv-subtext" style="margin-top:4px;">
                                            <?php echo $doc->storage_driver === 'google_drive' ? '☁️ Google Drive' : '🔒 Local'; ?>
                                            · 👁️ <?php echo (int) $doc->verified_count; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="aura-dv-row-actions">
                                            <button type="button" class="btn btn-primary btn-sm btn-lift aura-dv-open-detail"
                                                    data-id="<?php echo esc_attr( (string) $doc->id ); ?>"
                                                    title="<?php esc_attr_e( 'QR y Ficha 360°', 'aura-suite' ); ?>">
                                                <span class="dashicons dashicons-smartphone"></span> QR
                                            </button>
                                            <?php if ( $can_download ) : ?>
                                                <a href="<?php echo esc_url( $dl_stamp ); ?>" class="btn btn-secondary btn-sm btn-lift"
                                                   title="<?php esc_attr_e( 'Descargar PDF sellado / certificado', 'aura-suite' ); ?>">
                                                    <span class="dashicons dashicons-download"></span> PDF
                                                </a>
                                            <?php endif; ?>
                                            <?php if ( $can_edit ) : ?>
                                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-docverify-register&id=' . $doc->id ) ); ?>"
                                                   class="btn btn-glass btn-sm"
                                                   title="<?php esc_attr_e( 'Editar metadatos o vigencia', 'aura-suite' ); ?>">
                                                    <span class="dashicons dashicons-edit"></span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="aura-dv-child-row" data-parent-id="<?php echo esc_attr( (string) $doc->id ); ?>" style="display:none;">
                                    <td colspan="8">
                                        <div class="aura-dv-child-card">
                                            <div class="aura-dv-child-grid">
                                                <div>
                                                    <small><?php esc_html_e( 'SHA-256 Original', 'aura-suite' ); ?></small>
                                                    <code><?php echo esc_html( $doc->sha256_original ?: '—' ); ?></code>
                                                </div>
                                                <div>
                                                    <small><?php esc_html_e( 'SHA-256 Sellado / Anexo', 'aura-suite' ); ?></small>
                                                    <code><?php echo esc_html( $doc->sha256_stamped ?: '—' ); ?></code>
                                                </div>
                                                <div>
                                                    <small><?php esc_html_e( 'Firmante Autorizado', 'aura-suite' ); ?></small>
                                                    <strong><?php echo esc_html( trim( $doc->signer_name . ( $doc->signer_title ? ' — ' . $doc->signer_title : '' ) ) ?: '—' ); ?></strong>
                                                </div>
                                                <div>
                                                    <small><?php esc_html_e( 'URL de Verificación Pública', 'aura-suite' ); ?></small>
                                                    <div class="aura-dv-inline-copy">
                                                        <code><?php echo esc_html( $doc->verify_url ); ?></code>
                                                        <button type="button" class="btn btn-secondary btn-sm aura-dv-copy-btn" data-copy="<?php echo esc_attr( $doc->verify_url ); ?>">
                                                            <?php esc_html_e( 'Copiar URL', 'aura-suite' ); ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ( $listing['total_pages'] > 1 ) : ?>
                    <div class="aura-tablenav-modern">
                        <span>
                            <?php
                            printf(
                                /* translators: 1: página actual, 2: total páginas, 3: total registros */
                                esc_html__( 'Página %1$d de %2$d (%3$d documentos)', 'aura-suite' ),
                                $listing['page'],
                                $listing['total_pages'],
                                $listing['total']
                            );
                            ?>
                        </span>
                        <div class="aura-dv-pagination">
                            <?php
                            echo paginate_links( [
                                'base'      => add_query_arg( 'paged', '%#%' ),
                                'format'    => '',
                                'current'   => $listing['page'],
                                'total'     => $listing['total_pages'],
                                'prev_text' => '‹',
                                'next_text' => '›',
                            ] );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="adp-footer">
            <p>
                <?php esc_html_e( 'Desarrollado con', 'aura-suite' ); ?> ❤️ <?php esc_html_e( 'por', 'aura-suite' ); ?>
                <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link">DiGiraldo</a></strong>
                &nbsp;|&nbsp; © <?php echo esc_html( gmdate( 'Y' ) ); ?> AURA Business Suite
            </p>
        </div>
    </div>
</div>

<!-- Modal Ficha 360° + QR (Item 26 + Skeleton Asíncrono + Guardia 2 Fases) -->
<div id="aura-docverify-detail-modal" class="aura-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="aura-dv-modal-title" style="display:none;">
    <div class="aura-modal-container aura-dv-modal-container">
        <div class="aura-modal-header">
            <div>
                <h2 id="aura-dv-modal-title">🛡️ <?php esc_html_e( 'Credencial de Verificación y Código QR', 'aura-suite' ); ?></h2>
            </div>
            <button type="button" class="aura-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>
        <div class="aura-modal-body" id="aura-docverify-detail-body">
            <!-- Rellenado dinámicamente vía AJAX tras AuraUI.hideModalSkeleton() -->
        </div>
        <div class="aura-modal-footer">
            <div class="aura-dv-modal-footer-left">
                <?php if ( $can_revoke ) : ?>
                    <button type="button" id="aura-dv-btn-open-revoke" class="btn btn-amber btn-sm" style="display:none;">
                        <span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Revocar Documento', 'aura-suite' ); ?>
                    </button>
                    <button type="button" id="aura-dv-btn-reactivate" class="btn btn-emerald btn-sm" style="display:none;">
                        <span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Reactivar', 'aura-suite' ); ?>
                    </button>
                <?php endif; ?>
                <?php if ( $can_delete ) : ?>
                    <button type="button" id="aura-dv-btn-delete" class="btn btn-rose btn-sm">
                        <span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Eliminar', 'aura-suite' ); ?>
                    </button>
                <?php endif; ?>
            </div>
            <div class="aura-dv-modal-footer-right">
                <button type="button" class="btn btn-secondary aura-dv-modal-close-btn"><?php esc_html_e( 'Cerrar', 'aura-suite' ); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Revocación -->
<div id="aura-docverify-revoke-modal" class="aura-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="aura-dv-revoke-title" style="display:none;">
    <div class="aura-modal-container" style="max-width:520px !important;">
        <div class="aura-modal-header">
            <h2 id="aura-dv-revoke-title">⚠️ <?php esc_html_e( 'Revocar Autenticidad del Documento', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>
        <form id="aura-docverify-revoke-form">
            <div class="aura-modal-body">
                <input type="hidden" id="aura-dv-revoke-id" value="0">
                <div class="alert-card alert-warning">
                    <span class="alert-icon">⚠️</span>
                    <div>
                        <div class="alert-title"><?php esc_html_e( 'Efecto inmediato en la verificación pública', 'aura-suite' ); ?></div>
                        <p style="margin:4px 0 0;"><?php esc_html_e( 'Cualquier embajada o entidad que escanee el código QR verá el documento como REVOCADO junto con este motivo.', 'aura-suite' ); ?></p>
                    </div>
                </div>
                <label for="aura-dv-revoke-reason" class="aura-dv-label"><?php esc_html_e( 'Motivo oficial de revocación *', 'aura-suite' ); ?></label>
                <textarea id="aura-dv-revoke-reason" class="form-control" rows="3" required
                          placeholder="<?php esc_attr_e( 'Ej: Carta cancelada por cambio de fechas de evento / reemplazada por folio INV-2026-0015.', 'aura-suite' ); ?>"></textarea>
            </div>
            <div class="aura-modal-footer">
                <button type="button" class="btn btn-secondary aura-dv-modal-close-btn"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
                <button type="submit" class="btn btn-rose btn-shimmer">
                    <span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Confirmar Revocación', 'aura-suite' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>
