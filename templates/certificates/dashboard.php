<?php
/**
 * Template: Dashboard de Certificados
 *
 * @package AuraBusinessSuite
 * @var array $stats     KPIs calculados.
 * @var array $recents   Últimos certificados emitidos.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Calcular estadísticas
global $wpdb;
$table = $wpdb->prefix . 'aura_certificates';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$stats = [
    'total_active'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" ),
    'total_revoked' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'revoked'" ),
    'this_month'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'active' AND MONTH(issued_at) = %d AND YEAR(issued_at) = %d", date('n'), date('Y') ) ),
    'this_year'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'active' AND YEAR(issued_at) = %d", date('Y') ) ),
];

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$recents = $wpdb->get_results(
    "SELECT c.id, c.folio, c.course_name, c.issued_at, c.status,
            s.first_name, s.last_name
     FROM {$table} c
     LEFT JOIN {$wpdb->prefix}aura_students s ON c.student_id = s.id
     ORDER BY c.issued_at DESC LIMIT 10"
);

// Pendientes de emitir: inscripciones completadas o graduadas sin certificado
$enroll_table   = $wpdb->prefix . 'aura_student_enrollments';
$students_table = $wpdb->prefix . 'aura_students';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$pending_count  = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$enroll_table} e
     INNER JOIN {$students_table} s ON e.student_id = s.id
     WHERE (e.status = 'completed' OR s.status = 'graduated')
     AND NOT EXISTS (
         SELECT 1 FROM {$table} c WHERE c.enrollment_id = e.id AND c.status = 'active'
     )"
);
?>
<div class="wrap aura-app-container aura-certificates-wrap">

    <!-- ══════════════════ CABECERA ══════════════════ -->
    <?php
    Aura_UI::render_page_header([
        'variant'  => 'wow-vip',
        'title'    => __( 'Certificados y Diplomas', 'aura-suite' ),
        'subtitle' => __( 'Emisión, verificación y gestión de acreditaciones académicas e institucionales.', 'aura-suite' ),
        'icon'     => 'dashicons-awards',
        'badge'    => __( 'Certificaciones', 'aura-suite' ),
        'actions'  => [
            [
                'label'   => __( 'Emitir Certificado', 'aura-suite' ),
                'url'     => admin_url( 'admin.php?page=aura-certificates-list&action=issue' ),
                'class'   => 'aura-btn aura-btn-primary',
                'icon'    => 'dashicons-plus-alt',
            ],
            [
                'label'   => __( 'Emisión Masiva', 'aura-suite' ),
                'url'     => admin_url( 'admin.php?page=aura-certificates-bulk' ),
                'class'   => 'aura-btn aura-btn-secondary',
                'icon'    => 'dashicons-groups',
            ],
        ],
    ]);
    ?>

    <!-- ══════════════════ KPIS ══════════════════ -->
    <div style="margin-top: var(--aura-space-6, 24px);">
        <?php
        Aura_UI::render_stats_grid([
            [
                'label' => __( 'Total Activos', 'aura-suite' ),
                'value' => number_format_i18n( $stats['total_active'] ),
                'icon'  => 'dashicons-awards',
                'color' => 'primary',
            ],
            [
                'label' => __( 'Emitidos Este Mes', 'aura-suite' ),
                'value' => number_format_i18n( $stats['this_month'] ),
                'icon'  => 'dashicons-calendar-alt',
                'color' => 'success',
            ],
            [
                'label' => __( 'Emitidos Este Año', 'aura-suite' ),
                'value' => number_format_i18n( $stats['this_year'] ),
                'icon'  => 'dashicons-chart-line',
                'color' => 'info',
            ],
            [
                'label' => __( 'Pendientes de Emitir', 'aura-suite' ),
                'value' => number_format_i18n( $pending_count ),
                'icon'  => 'dashicons-clock',
                'color' => $pending_count > 0 ? 'warning' : 'secondary',
            ],
            [
                'label' => __( 'Revocados', 'aura-suite' ),
                'value' => number_format_i18n( $stats['total_revoked'] ),
                'icon'  => 'dashicons-dismiss',
                'color' => 'danger',
            ],
        ]);
        ?>
    </div>

    <?php if ( $pending_count > 0 ) : ?>
    <div class="aura-callout" style="margin-top: var(--aura-space-5, 20px); padding: 14px 18px; border-radius: var(--aura-radius-md, 8px); background: #fffbeb; border: 1px solid #fde68a; color: #92400e; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="dashicons dashicons-warning" style="color:#d97706;"></span>
            <span>
                <?php
                printf(
                    /* translators: %d número de graduados */
                    esc_html__( 'Hay %s estudiante(s) graduados sin certificado emitido.', 'aura-suite' ),
                    '<strong>' . esc_html( number_format_i18n( $pending_count ) ) . '</strong>'
                );
                ?>
            </span>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-certificates-bulk' ) ); ?>" class="aura-btn aura-btn-sm aura-btn-secondary" style="font-weight: 600;">
            <?php esc_html_e( 'Ir a emisión masiva →', 'aura-suite' ); ?>
        </a>
    </div>
    <?php endif; ?>

    <!-- ══════════════════ ÚLTIMOS EMITIDOS ══════════════════ -->
    <div class="aura-card" style="margin-top: var(--aura-space-6, 24px); padding: var(--aura-space-6, 24px);">
        <div class="aura-card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: var(--aura-space-4, 16px); padding-bottom: var(--aura-space-3, 12px); border-bottom: 1px solid var(--aura-border, #e2e8f0);">
            <h3 class="aura-card-title" style="margin: 0; font-size: var(--aura-text-base, 1rem); font-weight: 600; color: var(--aura-primary-900, #4c1d95); display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-list-view" style="color: var(--aura-primary-600, #7c3aed);"></span>
                <?php esc_html_e( 'Últimos Certificados Emitidos', 'aura-suite' ); ?>
            </h3>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-certificates-list' ) ); ?>" class="aura-btn aura-btn-sm aura-btn-secondary">
                <?php esc_html_e( 'Ver todos los certificados →', 'aura-suite' ); ?>
            </a>
        </div>

        <?php if ( empty( $recents ) ) : ?>
            <div style="text-align: center; padding: 48px 16px; color: var(--aura-text-secondary, #64748b);">
                <span class="dashicons dashicons-awards" style="font-size: 36px; width: 36px; height: 36px; color: var(--aura-border, #cbd5e1); margin-bottom: 12px;"></span>
                <p style="margin: 0; font-size: 14px;"><?php esc_html_e( 'Aún no se han emitido certificados.', 'aura-suite' ); ?></p>
            </div>
        <?php else : ?>
        <div class="aura-table-responsive">
            <table class="aura-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Folio', 'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Estudiante', 'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Curso / Programa', 'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Fecha Emisión', 'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                        <th style="text-align:right;"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recents as $cert ) : ?>
                    <tr>
                        <td><code style="background: var(--aura-primary-50, #f5f3ff); color: var(--aura-primary-800, #5b21b6); padding: 2px 8px; border-radius: var(--aura-radius-sm, 4px); font-weight: 600; font-size: 12px;"><?php echo esc_html( $cert->folio ); ?></code></td>
                        <td style="font-weight: 500;"><?php echo esc_html( trim( $cert->first_name . ' ' . $cert->last_name ) ); ?></td>
                        <td><?php echo esc_html( $cert->course_name ); ?></td>
                        <td style="color: var(--aura-text-secondary, #64748b); font-size: 13px;"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $cert->issued_at ) ) ); ?></td>
                        <td>
                            <?php if ( $cert->status === 'active' ) : ?>
                                <span class="aura-badge aura-badge-success"><?php esc_html_e( 'Activo', 'aura-suite' ); ?></span>
                            <?php else : ?>
                                <span class="aura-badge aura-badge-danger"><?php esc_html_e( 'Revocado', 'aura-suite' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <div class="aura-row-actions" style="justify-content: flex-end;">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-certificates-list&action=view&id=' . $cert->id ) ); ?>"
                                   class="aura-btn aura-btn-sm aura-btn-secondary" title="<?php esc_attr_e( 'Ver Certificado', 'aura-suite' ); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php esc_html_e( 'Ver', 'aura-suite' ); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
