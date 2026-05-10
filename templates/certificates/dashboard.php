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

// Pendientes de emitir: inscripciones graduadas sin certificado
$enroll_table   = $wpdb->prefix . 'aura_enrollments';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$pending_count  = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$enroll_table} e
     WHERE e.status = 'graduated'
     AND NOT EXISTS (
         SELECT 1 FROM {$table} c WHERE c.enrollment_id = e.id AND c.status = 'active'
     )"
);
?>
<div class="wrap aura-certificates-wrap">
    <h1 class="wp-heading-inline">🏅 <?php esc_html_e( 'Certificados y Diplomas', 'aura-suite' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-certificates-list&action=issue' ) ); ?>" class="page-title-action">
        <?php esc_html_e( '+ Emitir Certificado', 'aura-suite' ); ?>
    </a>
    <hr class="wp-header-end">

    <!-- KPIs -->
    <div class="aura-cert-kpis">
        <div class="aura-cert-kpi">
            <span class="aura-cert-kpi-value"><?php echo esc_html( number_format_i18n( $stats['total_active'] ) ); ?></span>
            <span class="aura-cert-kpi-label"><?php esc_html_e( 'Total Activos', 'aura-suite' ); ?></span>
        </div>
        <div class="aura-cert-kpi">
            <span class="aura-cert-kpi-value"><?php echo esc_html( number_format_i18n( $stats['this_month'] ) ); ?></span>
            <span class="aura-cert-kpi-label"><?php esc_html_e( 'Este Mes', 'aura-suite' ); ?></span>
        </div>
        <div class="aura-cert-kpi">
            <span class="aura-cert-kpi-value"><?php echo esc_html( number_format_i18n( $stats['this_year'] ) ); ?></span>
            <span class="aura-cert-kpi-label"><?php esc_html_e( 'Este Año', 'aura-suite' ); ?></span>
        </div>
        <div class="aura-cert-kpi <?php echo $pending_count > 0 ? 'aura-cert-kpi--warn' : ''; ?>">
            <span class="aura-cert-kpi-value"><?php echo esc_html( number_format_i18n( $pending_count ) ); ?></span>
            <span class="aura-cert-kpi-label"><?php esc_html_e( 'Pendientes de Emitir', 'aura-suite' ); ?></span>
        </div>
        <div class="aura-cert-kpi aura-cert-kpi--muted">
            <span class="aura-cert-kpi-value"><?php echo esc_html( number_format_i18n( $stats['total_revoked'] ) ); ?></span>
            <span class="aura-cert-kpi-label"><?php esc_html_e( 'Revocados', 'aura-suite' ); ?></span>
        </div>
    </div>

    <?php if ( $pending_count > 0 ) : ?>
    <div class="notice notice-warning inline" style="margin-top:16px;">
        <p>
            <?php
            printf(
                /* translators: 1=número, 2=URL */
                esc_html__( 'Hay %1$d estudiante(s) graduados sin certificado emitido.', 'aura-suite' ),
                esc_html( number_format_i18n( $pending_count ) )
            );
            ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-certificates-bulk' ) ); ?>">
                <?php esc_html_e( 'Ir a emisión masiva', 'aura-suite' ); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <!-- Últimos emitidos -->
    <div class="aura-cert-dashboard-section">
        <h2><?php esc_html_e( 'Últimos Certificados Emitidos', 'aura-suite' ); ?></h2>

        <?php if ( empty( $recents ) ) : ?>
            <p class="aura-empty"><?php esc_html_e( 'Aún no se han emitido certificados.', 'aura-suite' ); ?></p>
        <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Folio', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Estudiante', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Curso', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Fecha Emisión', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $recents as $cert ) : ?>
                <tr>
                    <td><code><?php echo esc_html( $cert->folio ); ?></code></td>
                    <td><?php echo esc_html( trim( $cert->first_name . ' ' . $cert->last_name ) ); ?></td>
                    <td><?php echo esc_html( $cert->course_name ); ?></td>
                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $cert->issued_at ) ) ); ?></td>
                    <td>
                        <span class="aura-cert-status aura-cert-status--<?php echo esc_attr( $cert->status ); ?>">
                            <?php echo $cert->status === 'active' ? esc_html__( 'Activo', 'aura-suite' ) : esc_html__( 'Revocado', 'aura-suite' ); ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-certificates-list&action=view&id=' . $cert->id ) ); ?>"
                           class="button button-small">
                            <?php esc_html_e( 'Ver', 'aura-suite' ); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top:8px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-certificates-list' ) ); ?>">
                <?php esc_html_e( 'Ver todos los certificados →', 'aura-suite' ); ?>
            </a>
        </p>
        <?php endif; ?>
    </div>
</div>
