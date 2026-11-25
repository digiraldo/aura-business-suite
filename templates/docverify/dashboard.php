<?php
/**
 * Template: Dashboard de Validación Documental
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$kpis        = Aura_DocVerify_Manager::get_kpis();
$recent_docs = Aura_DocVerify_Manager::list_documents( [ 'per_page' => 6, 'page' => 1 ] );
$recent_logs = Aura_DocVerify_Manager::list_logs( [ 'per_page' => 6, 'page' => 1 ] );
$drive_ready = Aura_DocVerify_Manager::is_drive_available();
$doc_types   = Aura_DocVerify_Settings::get_document_types();
?>
<div class="aura-app-wrapper aura-docverify-wrap" id="aura-docverify-dashboard">
    <div class="wrap aura-app-context">

        <?php
        $dv_actions = [];
        if ( current_user_can( 'aura_docverify_view_all' ) || current_user_can( 'aura_docverify_create' ) || current_user_can( 'manage_options' ) ) {
            $dv_actions[] = [
                'label' => __( 'Ver Documentos', 'aura-suite' ),
                'url'   => admin_url( 'admin.php?page=aura-docverify-list' ),
                'class' => 'btn btn-glass btn-sm btn-lift',
                'icon'  => 'dashicons-media-document',
            ];
        }
        if ( current_user_can( 'aura_docverify_audit' ) || current_user_can( 'manage_options' ) ) {
            $dv_actions[] = [
                'label' => __( 'Auditoría', 'aura-suite' ),
                'url'   => admin_url( 'admin.php?page=aura-docverify-audit' ),
                'class' => 'btn btn-glass btn-sm btn-lift',
                'icon'  => 'dashicons-visibility',
            ];
        }
        if ( current_user_can( 'aura_docverify_create' ) || current_user_can( 'manage_options' ) ) {
            $dv_actions[] = [
                'label' => __( 'Registrar PDF Firmado', 'aura-suite' ),
                'url'   => admin_url( 'admin.php?page=aura-docverify-register' ),
                'class' => 'btn btn-primary btn-shimmer btn-sm btn-lift',
                'icon'  => 'dashicons-plus-alt2',
            ];
        }

        Aura_UI::render_page_header( [
            'variant'  => 'wow-vip',
            'title'    => __( 'Validación Documental — Dashboard', 'aura-suite' ),
            'subtitle' => __( 'Control de documentos oficiales firmados, cartas de invitación para embajadas, códigos QR y cotejo SHA-256', 'aura-suite' ),
            'icon'     => 'dashicons-shield',
            'actions'  => $dv_actions,
        ] );

        Aura_UI::render_stats_grid( [
            [
                'id'      => 'dv-kpi-total',
                'icon'    => 'dashicons-media-document',
                'value'   => number_format_i18n( $kpis['total'] ),
                'label'   => __( 'Documentos Registrados', 'aura-suite' ),
                'variant' => 'capital',
                'tooltip' => __( 'Total histórico de documentos con folio y QR asignados', 'aura-suite' ),
            ],
            [
                'id'      => 'dv-kpi-active',
                'icon'    => 'dashicons-yes-alt',
                'value'   => number_format_i18n( $kpis['active'] ),
                'label'   => __( 'Vigentes y Válidos', 'aura-suite' ),
                'variant' => 'income',
                'tooltip' => __( 'Documentos activos dentro de su período de vigencia', 'aura-suite' ),
            ],
            [
                'id'      => 'dv-kpi-expiring',
                'icon'    => 'dashicons-clock',
                'value'   => number_format_i18n( $kpis['expiring_soon'] ),
                'label'   => __( 'Vencen en 15 días', 'aura-suite' ),
                'variant' => 'pending',
                'tooltip' => __( 'Documentos próximos a expirar (cartas de invitación o permisos)', 'aura-suite' ),
            ],
            [
                'id'      => 'dv-kpi-revoked',
                'icon'    => 'dashicons-dismiss',
                'value'   => number_format_i18n( $kpis['revoked'] ),
                'label'   => __( 'Revocados', 'aura-suite' ),
                'variant' => 'expense',
                'tooltip' => __( 'Documentos anulados oficialmente por la institución', 'aura-suite' ),
            ],
            [
                'id'      => 'dv-kpi-drive',
                'icon'    => 'dashicons-cloud-saved',
                'value'   => number_format_i18n( $kpis['drive_count'] ),
                'label'   => __( 'En Google Drive', 'aura-suite' ),
                'variant' => 'balance',
                'tooltip' => __( 'Documentos respaldados en Google Drive de AURA', 'aura-suite' ),
            ],
            [
                'id'      => 'dv-kpi-verif',
                'icon'    => 'dashicons-smartphone',
                'value'   => number_format_i18n( $kpis['verifications_30d'] ),
                'label'   => __( 'Verificaciones (30 días)', 'aura-suite' ),
                'variant' => 'count',
                'tooltip' => __( 'Consultas públicas válidas realizadas mediante QR, folio o SHA-256', 'aura-suite' ),
            ],
        ], [ 'columns' => 6 ] );
        ?>

        <!-- Franja estado Google Drive + acceso rápido verificador -->
        <div class="aura-glass-card aura-dv-status-strip fade-up">
            <div class="aura-dv-status-strip__left">
                <?php if ( $drive_ready ) : ?>
                    <span class="live-chip live-online">
                        <span class="pulse-dot dot-online"></span>
                        <?php esc_html_e( 'Google Drive Conectado (Almacenamiento Privado Activo)', 'aura-suite' ); ?>
                    </span>
                <?php else : ?>
                    <span class="live-chip live-warning">
                        <span class="pulse-dot dot-warning"></span>
                        <?php esc_html_e( 'Google Drive no configurado — Usando bóveda local protegida (.htaccess)', 'aura-suite' ); ?>
                    </span>
                <?php endif; ?>
                <span class="aura-dv-status-note">
                    <?php
                    printf(
                        /* translators: %s = nombre carpeta Drive */
                        esc_html__( 'Carpeta destino en Drive: %s / Año / Mes', 'aura-suite' ),
                        '<strong>' . esc_html( (string) Aura_DocVerify_Settings::get( 'drive_folder_name', 'Validación Documental' ) ) . '</strong>'
                    );
                    ?>
                </span>
            </div>
            <div class="aura-dv-status-strip__right">
                <?php
                $verify_page_id = class_exists( 'Aura_Page_Manager' ) ? Aura_Page_Manager::get_id( 'doc_verify' ) : 0;
                $portal_url     = $verify_page_id ? get_permalink( $verify_page_id ) : home_url( '/' . Aura_DocVerify_Settings::get( 'verify_slug', 'verificar-documento' ) . '/' );
                ?>
                <a href="<?php echo esc_url( $portal_url ); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm btn-lift">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e( 'Abrir Verificador Público', 'aura-suite' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-docverify-settings' ) ); ?>" class="btn btn-glass btn-sm btn-lift">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'Configuración', 'aura-suite' ); ?>
                </a>
            </div>
        </div>

        <div class="aura-dv-dashboard-grid fade-up">
            <!-- Últimos documentos -->
            <div class="aura-glass-card">
                <div class="aura-dv-card-head">
                    <h3><span class="dashicons dashicons-media-document"></span> <?php esc_html_e( 'Últimos Documentos Registrados', 'aura-suite' ); ?></h3>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-docverify-list' ) ); ?>" class="btn btn-secondary btn-sm">
                        <?php esc_html_e( 'Ver todos', 'aura-suite' ); ?>
                    </a>
                </div>
                <?php if ( empty( $recent_docs['items'] ) ) : ?>
                    <div class="empty-card">
                        <div class="empty-icon">🛡️</div>
                        <h4><?php esc_html_e( 'Aún no hay documentos registrados', 'aura-suite' ); ?></h4>
                        <p><?php esc_html_e( 'Sube tu primer PDF firmado o carta de invitación para generar su folio institucional, código QR y huella SHA-256.', 'aura-suite' ); ?></p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-docverify-register' ) ); ?>" class="btn btn-primary btn-shimmer btn-lift">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e( 'Registrar Primer Documento', 'aura-suite' ); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <div class="aura-dt-wrapper">
                        <table class="aura-table table-row-hover-lift">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Folio', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Asunto / Titular', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Nube', 'aura-suite' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $recent_docs['items'] as $doc ) :
                                    $eff = Aura_DocVerify_Manager::get_effective_status( $doc );
                                    ?>
                                    <tr>
                                        <td><span class="badge badge-blue"><?php echo esc_html( $doc->folio ); ?></span></td>
                                        <td>
                                            <strong><?php echo esc_html( $doc->title_subject ); ?></strong>
                                            <div class="aura-dv-subtext">
                                                <?php echo esc_html( $doc->holder_name ); ?>
                                                <?php if ( ! empty( $doc->recipient_entity ) ) : ?>
                                                    · <em><?php echo esc_html( $doc->recipient_entity ); ?></em>
                                                <?php endif; ?>
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
                                        </td>
                                        <td>
                                            <?php if ( $doc->storage_driver === 'google_drive' ) : ?>
                                                <span class="badge badge-cyan badge-sm">☁️ Drive</span>
                                            <?php else : ?>
                                                <span class="badge badge-slate badge-sm">🔒 Local</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Últimas verificaciones / eventos -->
            <div class="aura-glass-card">
                <div class="aura-dv-card-head">
                    <h3><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Actividad y Consultas Recientes', 'aura-suite' ); ?></h3>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-docverify-audit' ) ); ?>" class="btn btn-secondary btn-sm">
                        <?php esc_html_e( 'Ver historial', 'aura-suite' ); ?>
                    </a>
                </div>
                <?php if ( empty( $recent_logs['items'] ) ) : ?>
                    <div class="empty-card">
                        <div class="empty-icon">🔍</div>
                        <h4><?php esc_html_e( 'Sin eventos de auditoría todavía', 'aura-suite' ); ?></h4>
                        <p><?php esc_html_e( 'Cada escaneo QR, búsqueda manual, cotejo SHA-256 o descarga se registrará aquí.', 'aura-suite' ); ?></p>
                    </div>
                <?php else : ?>
                    <div class="aura-dt-wrapper">
                        <table class="aura-table table-row-hover-lift">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Folio', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Evento', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Resultado', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $recent_logs['items'] as $log ) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html( $log->folio_queried ?: '—' ); ?></code></td>
                                        <td><?php echo esc_html( $log->event_type ); ?></td>
                                        <td>
                                            <?php
                                            $r_badge = in_array( $log->result, [ 'ok', 'valid' ], true ) ? 'badge-emerald' : ( $log->result === 'expired' ? 'badge-amber' : 'badge-rose' );
                                            ?>
                                            <span class="badge badge-sm <?php echo esc_attr( $r_badge ); ?>"><?php echo esc_html( $log->result ); ?></span>
                                        </td>
                                        <td><small><?php echo esc_html( mysql2date( 'd/m/Y H:i', $log->created_at ) ); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
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
