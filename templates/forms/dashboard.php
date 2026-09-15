<?php
/**
 * Dashboard — Módulo de Formularios y Encuestas
 *
 * Muestra KPIs clave, resumen de actividad reciente y accesos rápidos.
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'aura_forms_view_responses_all' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
}

global $wpdb;

// ── KPI 1: Formularios activos ────────────────────────────────
$active_forms = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}aura_forms WHERE is_active = 1 AND deleted_at IS NULL"
);
$total_forms  = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}aura_forms WHERE deleted_at IS NULL"
);

// ── KPI 2: Submissions este mes ───────────────────────────────
$month_start = gmdate( 'Y-m-01 00:00:00' );
$subs_month  = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}aura_form_submissions WHERE submitted_at >= %s",
        $month_start
    )
);
$subs_total = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}aura_form_submissions"
);

// ── KPI 3: Inscripciones pendientes ───────────────────────────
$pending_enrollments = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}aura_student_enrollments WHERE status = 'pending' OR status = 'pending_review'"
);

// ── KPI 4: Encuestas pendientes de responder ──────────────────
$pending_surveys = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}aura_form_assignments WHERE status = 'pending'"
);

// ── Actividad últimos 7 días ─────────────────────────────────
$last7 = [];
for ( $i = 6; $i >= 0; $i-- ) {
    $day_key = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
    $last7[ $day_key ] = 0;
}

$rows_7d = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT DATE(submitted_at) AS day, COUNT(*) AS cnt
           FROM {$wpdb->prefix}aura_form_submissions
          WHERE submitted_at >= %s
       GROUP BY DATE(submitted_at)",
        gmdate( 'Y-m-d', strtotime( '-6 days' ) ) . ' 00:00:00'
    )
);

foreach ( $rows_7d as $row ) {
    if ( isset( $last7[ $row->day ] ) ) {
        $last7[ $row->day ] = (int) $row->cnt;
    }
}

$max7 = max( array_values( $last7 ) ?: [ 1 ] );

// ── Formularios más activos (top 5) ──────────────────────────
$top_forms = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT f.id, f.title, f.type, f.slug,
                COUNT(s.id) AS total
           FROM {$wpdb->prefix}aura_forms f
      LEFT JOIN {$wpdb->prefix}aura_form_submissions s ON s.form_id = f.id
          WHERE f.deleted_at IS NULL
       GROUP BY f.id
       ORDER BY total DESC
          LIMIT %d",
        5
    )
);

// ── Submissions recientes (últimas 5) ─────────────────────────
$recent_subs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT s.id, s.submitted_name, s.submitted_email, s.status, s.submitted_at,
                f.title AS form_title, f.id AS form_id
           FROM {$wpdb->prefix}aura_form_submissions s
           JOIN {$wpdb->prefix}aura_forms f ON f.id = s.form_id
       ORDER BY s.submitted_at DESC
          LIMIT %d",
        5
    )
);

$type_labels = [
    'generic'    => __( 'Genérico',    'aura-suite' ),
    'enrollment' => __( 'Inscripción', 'aura-suite' ),
    'survey'     => __( 'Encuesta',    'aura-suite' ),
    'feedback'   => __( 'Feedback',    'aura-suite' ),
];
$status_class = [
    'received' => 'aura-badge-info',
    'reviewed' => 'aura-badge-success',
    'spam'     => 'aura-badge-danger',
];
?>
<div class="wrap aura-app-container aura-forms-wrap aura-forms-dashboard">

    <!-- ══════════════════ CABECERA ══════════════════ -->
    <?php
    Aura_UI::render_page_header([
        'variant'  => 'wow-vip',
        'title'    => __( 'Formularios y Encuestas', 'aura-suite' ),
        'subtitle' => __( 'Gestión de formularios dinámicos, encuestas institucionales y postulaciones recibidas.', 'aura-suite' ),
        'icon'     => 'dashicons-feedback',
        'badge'    => __( 'Formularios', 'aura-suite' ),
        'actions'  => [
            [
                'label'   => __( 'Nuevo Formulario', 'aura-suite' ),
                'url'     => admin_url( 'admin.php?page=aura-forms-new' ),
                'class'   => 'aura-btn aura-btn-primary',
                'icon'    => 'dashicons-plus-alt',
            ],
            [
                'label'   => __( 'Revisar Postulantes', 'aura-suite' ),
                'url'     => admin_url( 'admin.php?page=aura-forms-enrollments' ),
                'class'   => 'aura-btn aura-btn-secondary',
                'icon'    => 'dashicons-groups',
            ],
        ],
    ]);
    ?>

    <!-- ── ══════════════════ KPI CARDS ══════════════════ ── -->
    <div style="margin-top: var(--aura-space-6, 24px);">
        <?php
        Aura_UI::render_stats_grid([
            [
                'label'    => __( 'Formularios Activos', 'aura-suite' ),
                'value'    => absint( $active_forms ),
                'icon'     => 'dashicons-feedback',
                'color'    => 'primary',
                'subtitle' => sprintf( __( '%d en total', 'aura-suite' ), absint( $total_forms ) ),
            ],
            [
                'label'    => __( 'Respuestas Este Mes', 'aura-suite' ),
                'value'    => absint( $subs_month ),
                'icon'     => 'dashicons-email-alt',
                'color'    => 'info',
                'subtitle' => sprintf( __( '%d en total', 'aura-suite' ), absint( $subs_total ) ),
            ],
            [
                'label'    => __( 'Inscripciones Pendientes', 'aura-suite' ),
                'value'    => absint( $pending_enrollments ),
                'icon'     => 'dashicons-groups',
                'color'    => $pending_enrollments > 0 ? 'warning' : 'success',
                'subtitle' => $pending_enrollments > 0 ? __( 'Requieren revisión', 'aura-suite' ) : __( 'Sin pendientes', 'aura-suite' ),
            ],
            [
                'label'    => __( 'Encuestas Asignadas', 'aura-suite' ),
                'value'    => absint( $pending_surveys ),
                'icon'     => 'dashicons-chart-bar',
                'color'    => $pending_surveys > 0 ? 'primary' : 'secondary',
                'subtitle' => __( 'Pendientes de completar', 'aura-suite' ),
            ],
        ]);
        ?>
    </div>

    <!-- ── ══════════════════ FILA PRINCIPAL: ACTIVIDAD Y TOP FORMS ══════════════════ ── -->
    <div style="display: grid; grid-template-columns: 2fr 1.2fr; gap: var(--aura-space-6, 24px); margin-top: var(--aura-space-6, 24px); align-items: start;">

        <!-- ── Actividad últimos 7 días ─────────────────────────── -->
        <div class="aura-card" style="padding: var(--aura-space-6, 24px);">
            <div class="aura-card-header" style="margin-bottom: var(--aura-space-5, 20px); padding-bottom: var(--aura-space-3, 12px); border-bottom: 1px solid var(--aura-border, #e2e8f0);">
                <h3 class="aura-card-title" style="margin: 0; font-size: var(--aura-text-base, 1rem); font-weight: 600; color: var(--aura-primary-900, #4c1d95); display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-chart-area" style="color: var(--aura-primary-600, #7c3aed);"></span>
                    <?php esc_html_e( 'Actividad — últimos 7 días', 'aura-suite' ); ?>
                </h3>
            </div>
            
            <div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; height: 160px; padding-top: 24px;">
                <?php foreach ( $last7 as $day => $cnt ) :
                    $pct   = $max7 > 0 ? round( ( $cnt / $max7 ) * 100 ) : 0;
                    $label = wp_date( 'D', strtotime( $day ) );
                ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; gap: 6px;">
                    <span style="font-size: 11px; font-weight: 600; color: var(--aura-text-secondary, #64748b);"><?php echo $cnt > 0 ? absint( $cnt ) : ''; ?></span>
                    <div style="width: 100%; max-width: 36px; background: var(--aura-surface-alt, #f1f5f9); border-radius: var(--aura-radius-md, 8px); height: 110px; display: flex; align-items: flex-end; overflow: hidden;">
                        <div style="width: 100%; height: <?php echo max( 4, $pct ); ?>%; background: linear-gradient(180deg, var(--aura-primary-500, #8b5cf6) 0%, var(--aura-primary-700, #6d28d9) 100%); border-radius: var(--aura-radius-sm, 4px); transition: height .4s ease;"></div>
                    </div>
                    <span style="font-size: 12px; font-weight: 500; color: var(--aura-text-secondary, #64748b);"><?php echo esc_html( $label ); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Top 5 formularios ───────────────────────────────── -->
        <div class="aura-card" style="padding: var(--aura-space-6, 24px);">
            <div class="aura-card-header" style="margin-bottom: var(--aura-space-4, 16px); padding-bottom: var(--aura-space-3, 12px); border-bottom: 1px solid var(--aura-border, #e2e8f0);">
                <h3 class="aura-card-title" style="margin: 0; font-size: var(--aura-text-base, 1rem); font-weight: 600; color: var(--aura-primary-900, #4c1d95); display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-star-filled" style="color: #f59e0b;"></span>
                    <?php esc_html_e( 'Formularios más activos', 'aura-suite' ); ?>
                </h3>
            </div>
            
            <?php if ( empty( $top_forms ) ) : ?>
                <p style="color: var(--aura-text-secondary, #64748b); text-align: center; padding: 24px 0; margin: 0; font-size: 14px;"><?php esc_html_e( 'Sin formularios registrados aún.', 'aura-suite' ); ?></p>
            <?php else : ?>
            <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ( $top_forms as $tf ) : ?>
                <li style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; background: var(--aura-surface-alt, #f8fafc); border: 1px solid var(--aura-border, #e2e8f0); border-radius: var(--aura-radius-md, 8px);">
                    <div style="display: flex; flex-direction: column; gap: 2px; overflow: hidden; margin-right: 8px;">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list&action=responses&id=' . $tf->id ) ); ?>" style="color: var(--aura-text-primary, #1e293b); font-weight: 600; font-size: 13px; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo esc_html( $tf->title ); ?>
                        </a>
                        <span style="font-size: 11px; color: var(--aura-text-muted, #94a3b8);"><?php echo esc_html( $type_labels[ $tf->type ] ?? $tf->type ); ?></span>
                    </div>
                    <span class="aura-badge aura-badge-primary" style="font-weight: 700; font-size: 12px;">
                        <?php echo absint( $tf->total ); ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

    </div>

    <!-- ── ══════════════════ RESPUESTAS RECIENTES ══════════════════ ── -->
    <div class="aura-card" style="margin-top: var(--aura-space-6, 24px); padding: var(--aura-space-6, 24px);">
        <div class="aura-card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: var(--aura-space-4, 16px); padding-bottom: var(--aura-space-3, 12px); border-bottom: 1px solid var(--aura-border, #e2e8f0);">
            <h3 class="aura-card-title" style="margin: 0; font-size: var(--aura-text-base, 1rem); font-weight: 600; color: var(--aura-primary-900, #4c1d95); display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-email-alt" style="color: var(--aura-primary-600, #7c3aed);"></span>
                <?php esc_html_e( 'Respuestas recientes', 'aura-suite' ); ?>
            </h3>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list' ) ); ?>" class="aura-btn aura-btn-sm aura-btn-secondary">
                <?php esc_html_e( 'Ver todas →', 'aura-suite' ); ?>
            </a>
        </div>

        <?php if ( empty( $recent_subs ) ) : ?>
            <div style="text-align: center; padding: 40px 16px; color: var(--aura-text-secondary, #64748b);">
                <span class="dashicons dashicons-email-alt" style="font-size: 36px; width: 36px; height: 36px; color: var(--aura-border, #cbd5e1); margin-bottom: 12px;"></span>
                <p style="margin: 0; font-size: 14px;"><?php esc_html_e( 'Aún no se han recibido respuestas.', 'aura-suite' ); ?></p>
            </div>
        <?php else : ?>
        <div class="aura-table-responsive">
            <table class="aura-table">
                <thead>
                    <tr>
                        <th style="width:70px">#</th>
                        <th><?php esc_html_e( 'Nombre / Remitente', 'aura-suite' ); ?></th>
                        <th><?php esc_html_e( 'Formulario', 'aura-suite' ); ?></th>
                        <th style="width:120px"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                        <th style="width:150px"><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent_subs as $rs ) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list&action=view-submission&sub_id=' . $rs->id . '&form_id=' . $rs->form_id ) ); ?>" style="font-weight: 600; color: var(--aura-primary-600, #7c3aed); text-decoration: none;">
                                #<?php echo absint( $rs->id ); ?>
                            </a>
                        </td>
                        <td>
                            <?php echo $rs->submitted_name
                                ? '<span style="font-weight:500;">' . esc_html( $rs->submitted_name ) . '</span>'
                                : '<em style="color:var(--aura-text-muted, #94a3b8);">' . esc_html__( '(anónimo)', 'aura-suite' ) . '</em>'; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list&action=responses&id=' . $rs->form_id ) ); ?>" style="color: var(--aura-text-primary, #1e293b); text-decoration: none; font-weight: 500;">
                                <?php echo esc_html( $rs->form_title ); ?>
                            </a>
                        </td>
                        <td>
                            <span class="aura-badge <?php echo esc_attr( $status_class[ $rs->status ] ?? 'aura-badge-secondary' ); ?>">
                                <?php echo esc_html( ucfirst( $rs->status ) ); ?>
                            </span>
                        </td>
                        <td style="color: var(--aura-text-secondary, #64748b); font-size: 13px;"><?php echo esc_html( wp_date( 'j M Y, H:i', strtotime( $rs->submitted_at ) ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── ══════════════════ ACCESOS RÁPIDOS ══════════════════ ── -->
    <div style="margin-top: var(--aura-space-6, 24px); display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-new' ) ); ?>" class="aura-btn aura-btn-secondary" style="justify-content: center; padding: 12px 14px;">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php esc_html_e( 'Nuevo formulario', 'aura-suite' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-enrollments' ) ); ?>" class="aura-btn aura-btn-secondary" style="justify-content: center; padding: 12px 14px;">
            <span class="dashicons dashicons-groups"></span>
            <?php esc_html_e( 'Revisar postulantes', 'aura-suite' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-assignments' ) ); ?>" class="aura-btn aura-btn-secondary" style="justify-content: center; padding: 12px 14px;">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e( 'Asignar encuestas', 'aura-suite' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-analytics' ) ); ?>" class="aura-btn aura-btn-secondary" style="justify-content: center; padding: 12px 14px;">
            <span class="dashicons dashicons-chart-area"></span>
            <?php esc_html_e( 'Análisis', 'aura-suite' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-reports' ) ); ?>" class="aura-btn aura-btn-secondary" style="justify-content: center; padding: 12px 14px;">
            <span class="dashicons dashicons-chart-bar"></span>
            <?php esc_html_e( 'Reportes', 'aura-suite' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-settings' ) ); ?>" class="aura-btn aura-btn-secondary" style="justify-content: center; padding: 12px 14px;">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e( 'Configuración', 'aura-suite' ); ?>
        </a>
    </div>

</div>
