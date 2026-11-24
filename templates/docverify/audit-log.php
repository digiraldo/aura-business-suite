<?php
/**
 * Template: Auditoría de Consultas y Eventos de Validación Documental
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$search     = sanitize_text_field( wp_unslash( $_GET['s_log'] ?? '' ) );
$event_type = sanitize_key( wp_unslash( $_GET['event_type'] ?? '' ) );
$paged      = max( 1, absint( $_GET['paged'] ?? 1 ) );

$audit = Aura_DocVerify_Manager::list_logs( [
    'search'     => $search,
    'event_type' => $event_type,
    'page'       => $paged,
    'per_page'   => 25,
] );
?>
<div class="aura-app-wrapper aura-docverify-wrap" id="aura-docverify-audit-page">
    <div class="wrap aura-app-context">

        <?php
        Aura_UI::render_page_header( [
            'title'    => __( 'Auditoría de Verificaciones y Consultas', 'aura-suite' ),
            'subtitle' => __( 'Trazabilidad completa de escaneos QR, búsquedas manuales, cotejos SHA-256, desbloqueos con PIN y descargas', 'aura-suite' ),
            'icon'     => 'dashicons-visibility',
            'badges'   => [
                [
                    'label'   => __( 'Registro Criptográfico Activo', 'aura-suite' ),
                    'variant' => 'emerald',
                ],
            ],
            'actions'  => [
                [
                    'label' => __( 'Documentos', 'aura-suite' ),
                    'url'   => admin_url( 'admin.php?page=aura-docverify-list' ),
                    'class' => 'btn btn-glass btn-sm btn-lift',
                    'icon'  => 'dashicons-media-document',
                ],
            ],
        ] );
        ?>

        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="aura-filters-card aura-glass-card">
            <input type="hidden" name="page" value="aura-docverify-audit">
            <div class="aura-dv-filters-grid aura-dv-filters-grid--audit">
                <div class="input-group">
                    <span class="input-group-text">🔍</span>
                    <input type="text" name="s_log" class="form-control" value="<?php echo esc_attr( $search ); ?>"
                           placeholder="<?php esc_attr_e( 'Buscar por folio, asunto o titular…', 'aura-suite' ); ?>">
                </div>

                <div class="input-group">
                    <span class="input-group-text">⚡</span>
                    <select name="event_type" class="form-select form-control">
                        <option value=""><?php esc_html_e( 'Todos los eventos', 'aura-suite' ); ?></option>
                        <option value="created" <?php selected( $event_type, 'created' ); ?>>created</option>
                        <option value="verify_qr" <?php selected( $event_type, 'verify_qr' ); ?>>verify_qr</option>
                        <option value="verify_manual" <?php selected( $event_type, 'verify_manual' ); ?>>verify_manual</option>
                        <option value="verify_hash_ok" <?php selected( $event_type, 'verify_hash_ok' ); ?>>verify_hash_ok</option>
                        <option value="verify_hash_fail" <?php selected( $event_type, 'verify_hash_fail' ); ?>>verify_hash_fail</option>
                        <option value="pin_unlock" <?php selected( $event_type, 'pin_unlock' ); ?>>pin_unlock</option>
                        <option value="download_pdf" <?php selected( $event_type, 'download_pdf' ); ?>>download_pdf</option>
                        <option value="revoked" <?php selected( $event_type, 'revoked' ); ?>>revoked</option>
                    </select>
                </div>

                <div class="aura-dv-filter-btns">
                    <button type="submit" class="btn btn-primary btn-shimmer">
                        <span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Filtrar', 'aura-suite' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-docverify-audit' ) ); ?>" class="btn btn-secondary">
                        <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
                    </a>
                </div>
            </div>
        </form>

        <div class="aura-glass-card">
            <?php if ( empty( $audit['items'] ) ) : ?>
                <div class="empty-card">
                    <div class="empty-icon">📜</div>
                    <h4><?php esc_html_e( 'Sin registros de auditoría para estos criterios', 'aura-suite' ); ?></h4>
                </div>
            <?php else : ?>
                <div class="aura-dt-wrapper aura-table-responsive-wrap">
                    <table class="aura-table table-row-hover-lift">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Fecha y Hora', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Folio', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Documento', 'aura-suite' ); ?></th>
                                <th>
                                    <span class="aura-th-flex">
                                        <?php esc_html_e( 'Evento', 'aura-suite' ); ?>
                                        <span class="aura-help-icon aura-tooltip-wrap" data-tooltip="<?php esc_attr_e( 'Tipo de operación: verify_qr (escaneo), verify_manual, verify_hash_ok/fail (cotejo PDF), pin_unlock, download_pdf o revoked.', 'aura-suite' ); ?>">?</span>
                                    </span>
                                </th>
                                <th>
                                    <span class="aura-th-flex">
                                        <?php esc_html_e( 'Resultado', 'aura-suite' ); ?>
                                        <span class="aura-help-icon aura-tooltip-wrap" data-tooltip="<?php esc_attr_e( 'Indica si la validación fue válida/ok, expirada, revocada, con token inválido o PIN incorrecto.', 'aura-suite' ); ?>">?</span>
                                    </span>
                                </th>
                                <th>
                                    <span class="aura-th-flex">
                                        <?php esc_html_e( 'Detalle Técnico', 'aura-suite' ); ?>
                                        <span class="aura-help-icon aura-tooltip-wrap" data-tooltip="<?php esc_attr_e( 'Huella anonimizada de la IP solicitante y metadatos del evento.', 'aura-suite' ); ?>">?</span>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $audit['items'] as $row ) :
                                $badge_cls = in_array( $row->result, [ 'ok', 'valid' ], true ) ? 'badge-emerald' : ( $row->result === 'expired' ? 'badge-amber' : 'badge-rose' );
                                ?>
                                <tr>
                                    <td><small><?php echo esc_html( mysql2date( 'd/m/Y H:i:s', $row->created_at ) ); ?></small></td>
                                    <td><span class="badge badge-blue"><?php echo esc_html( $row->folio_queried ?: '—' ); ?></span></td>
                                    <td>
                                        <strong><?php echo esc_html( $row->title_subject ?: __( '(No localizado)', 'aura-suite' ) ); ?></strong>
                                        <?php if ( ! empty( $row->holder_name ) ) : ?>
                                            <div class="aura-dv-subtext"><?php echo esc_html( $row->holder_name ); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo esc_html( $row->event_type ); ?></code></td>
                                    <td><span class="badge badge-sm <?php echo esc_attr( $badge_cls ); ?>"><?php echo esc_html( $row->result ); ?></span></td>
                                    <td>
                                        <small class="aura-dv-subtext">
                                            <?php
                                            if ( ! empty( $row->ip_hash ) ) {
                                                echo 'IP#' . esc_html( substr( $row->ip_hash, 0, 10 ) );
                                            }
                                            if ( ! empty( $row->meta_json ) ) {
                                                echo ' · ' . esc_html( substr( $row->meta_json, 0, 90 ) );
                                            }
                                            ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ( $audit['total_pages'] > 1 ) : ?>
                    <div class="aura-tablenav-modern">
                        <span><?php printf( esc_html__( 'Página %1$d de %2$d (%3$d eventos)', 'aura-suite' ), $audit['page'], $audit['total_pages'], $audit['total'] ); ?></span>
                        <div class="aura-dv-pagination">
                            <?php
                            echo paginate_links( [
                                'base'      => add_query_arg( 'paged', '%#%' ),
                                'format'    => '',
                                'current'   => $audit['page'],
                                'total'     => $audit['total_pages'],
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
